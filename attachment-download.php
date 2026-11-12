<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_user();
require_once __DIR__ . '/includes/reflection-format.php';
require_once __DIR__ . '/includes/attachments.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD'); http_response_code(405); exit('Phương thức không được hỗ trợ.');
}
$id = reflection_id($_GET['id'] ?? null);
try {
    $q = db()->prepare("SELECT a.Id, a.FileName, a.ObjectKey, a.ContentType, a.FileSize FROM Attachments a JOIN Reflections r ON r.Id = a.ReflectionId JOIN Users actor ON actor.Id = ? AND actor.IsActive = 1 WHERE a.Id = ? AND (actor.Role = 'Staff' OR (actor.Role = 'Student' AND r.UserId = actor.Id))");
    $q->execute([(int) $user['Id'], $id ?? 0]);
    $file = $q->fetch();
    if (!$file || !preg_match('/^[a-f0-9]{64}\.(pdf|jpg|png)$/D', $file['ObjectKey']) || !in_array($file['ContentType'], ['application/pdf', 'image/jpeg', 'image/png'], true)) {
        http_response_code(404); exit('Không tìm thấy tệp hoặc bạn không có quyền tải xuống.');
    }
    $directory = attachment_storage_dir();
    $path = realpath($directory . DIRECTORY_SEPARATOR . $file['ObjectKey']);
    if (!$path || dirname($path) !== $directory || !is_file($path) || filesize($path) !== (int) $file['FileSize']) {
        http_response_code(404); exit('Không tìm thấy tệp.');
    }
    $stream = @fopen($path, 'rb');
    if (!$stream) { http_response_code(503); exit('Chưa đọc được tệp. Vui lòng thử lại.'); }
    header('Content-Type: ' . $file['ContentType']);
    header('Content-Length: ' . $file['FileSize']);
    header('Content-Disposition: attachment; filename="campusvoice-' . (int) $file['Id'] . '.' . pathinfo($file['ObjectKey'], PATHINFO_EXTENSION) . '"; filename*=UTF-8\'\'' . rawurlencode($file['FileName']));
    header('X-Content-Type-Options: nosniff');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') { fpassthru($stream); }
    fclose($stream);
} catch (PDOException | RuntimeException $exception) {
    error_log((string) $exception); http_response_code(503); exit('Chưa tải được tệp. Vui lòng thử lại.');
}
