<?php
const ATTACHMENT_MAX_BYTES = 5 * 1024 * 1024;

function upload_ini_bytes(string $value): int
{
    $value = trim($value);
    $units = ['g' => 1073741824, 'm' => 1048576, 'k' => 1024];
    return (int) ((float) $value * ($units[strtolower(substr($value, -1))] ?? 1));
}

function attachment_post_too_large(): bool
{
    $limit = upload_ini_bytes((string) ini_get('post_max_size'));
    return $limit > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit;
}

function validate_attachment_upload($file): ?array
{
    if ($file === null) { return null; }
    if (!is_array($file) || !isset($file['error']) || !is_int($file['error'])) {
        throw new InvalidArgumentException('Dữ liệu tệp đính kèm không hợp lệ.');
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) { return null; }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new InvalidArgumentException('Tệp vượt giới hạn tải lên. Tối đa 5 MiB; kiểm tra thêm upload_max_filesize của PHP.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Chưa nhận được tệp đầy đủ. Vui lòng chọn lại tệp.');
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size < 1 || $size > ATTACHMENT_MAX_BYTES) {
        throw new InvalidArgumentException('Tệp phải có dữ liệu và không quá 5 MiB.');
    }
    $name = is_string($file['name'] ?? null) ? $file['name'] : '';
    if (!mb_check_encoding($name, 'UTF-8')) { throw new InvalidArgumentException('Tên tệp không hợp lệ.'); }
    $name = basename(str_replace(chr(92), '/', $name));
    $name = trim(preg_replace('/[[:cntrl:]]/u', '', $name));
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $types = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($types[$extension]) || $types[$extension] !== $mime) {
        throw new InvalidArgumentException('Chỉ chấp nhận PDF, JPG, PNG có phần mở rộng và nội dung đúng loại tệp.');
    }
    if ($extension === 'pdf') {
        $handle = fopen($file['tmp_name'], 'rb');
        $signature = $handle ? fread($handle, 5) : '';
        if ($handle) { fclose($handle); }
        if ($signature !== '%PDF-') { throw new InvalidArgumentException('Nội dung PDF không hợp lệ.'); }
    } elseif (@getimagesize($file['tmp_name']) === false) {
        throw new InvalidArgumentException('Nội dung ảnh không hợp lệ.');
    }
    return ['tmp_name' => $file['tmp_name'], 'name' => mb_substr($name, 0, 200, 'UTF-8'), 'size' => $size, 'mime' => $mime, 'extension' => $extension === 'jpeg' ? 'jpg' : $extension];
}

function attachment_storage_dir(): string
{
    $config = require __DIR__ . '/../config/database.php';
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $config['name'])) { throw new RuntimeException('Tên database không phù hợp với cấu hình lưu tệp.'); }
    $directory = require __DIR__ . '/../config/uploads.php';
    if (!is_string($directory) || $directory === '') { throw new RuntimeException('Chưa cấu hình thư mục lưu tệp.'); }
    if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Không tạo được thư mục lưu tệp.');
    }
    $real = realpath($directory);
    $web = realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2));
    $normalized = strtolower(str_replace(chr(92), '/', $real ?: ''));
    $webNormalized = strtolower(str_replace(chr(92), '/', $web ?: ''));
    if (!$real || ($webNormalized !== '' && ($normalized === $webNormalized || strpos($normalized, $webNormalized . '/') === 0))) {
        throw new RuntimeException('Thư mục lưu tệp phải nằm ngoài thư mục web.');
    }
    return $real;
}

function attachment_remove_file(?string $path): void
{
    if ($path !== null && is_file($path) && !@unlink($path)) { error_log('CampusVoice: cannot remove attachment ' . $path); }
}

// Call inside the transaction; return the new physical path for rollback cleanup.
function attachment_store(PDO $pdo, int $reflectionId, ?array $upload): ?string
{
    if ($upload === null) { return null; }
    $key = bin2hex(random_bytes(32)) . '.' . $upload['extension'];
    $path = attachment_storage_dir() . DIRECTORY_SEPARATOR . $key;
    if (!move_uploaded_file($upload['tmp_name'], $path)) { throw new RuntimeException('Không lưu được tệp đính kèm.'); }
    try {
        $q = $pdo->prepare('INSERT INTO Attachments (FileName, ObjectKey, ContentType, FileSize, ReflectionId) VALUES (?, ?, ?, ?, ?)');
        $q->execute([$upload['name'], $key, $upload['mime'], $upload['size'], $reflectionId]);
        return $path;
    } catch (Throwable $exception) {
        attachment_remove_file($path);
        throw $exception;
    }
}

// The detail controller authorizes the parent reflection before calling this helper.
function render_reflection_attachments(PDO $pdo, int $id): void
{
    $q = $pdo->prepare('SELECT Id, FileName, FileSize FROM Attachments WHERE ReflectionId = ? ORDER BY UploadedAt, Id');
    $q->execute([$id]);
    $files = $q->fetchAll();
    ?><section class="info-card responses-section"><span class="section-tag">MINH CHỨNG</span><h2>Tệp đính kèm</h2><?php
    if (!$files) { ?><p class="muted">Chưa có tệp đính kèm.</p><?php }
    foreach ($files as $file) {
        ?><p><a class="detail-link" href="attachment-download.php?id=<?= (int) $file['Id'] ?>"><?= e($file['FileName']) ?></a> <span class="muted">(<?= e(number_format((float) $file['FileSize'] / 1024, 1)) ?> KiB)</span></p><?php
    }
    ?></section><?php
}

function attachment_upload_field(): void
{
    ?><label for="attachment">Tệp đính kèm (không bắt buộc)</label><input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"><p class="field-help">Một tệp PDF, JPG hoặc PNG, tối đa 5 MiB cho mỗi lần gửi. Nếu biểu mẫu có lỗi, vui lòng chọn lại tệp.</p><?php
}
