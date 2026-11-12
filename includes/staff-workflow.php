<?php
require_once __DIR__ . '/reflection-history.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/attachments.php';
const STAFF_REFLECTION_SCOPE = "EXISTS (SELECT 1 FROM Users staff_access WHERE staff_access.Id = ? AND staff_access.Role = 'Staff' AND staff_access.IsActive = 1)";
class StaffReflectionNotFound extends RuntimeException {}
class StaffWorkflowConflict extends RuntimeException {}

function staff_process_reflection(PDO $pdo, int $id, int $staffId, string $action, string $reply, int $version): void
{
    $transitions = ['accept' => ['Pending', 'InProgress'], 'resolve' => ['InProgress', 'Resolved'], 'request_info' => ['InProgress', 'NeedsInfo'], 'close' => ['Resolved', 'Closed']];
    if (!isset($transitions[$action])) { throw new InvalidArgumentException('Thao tác không hợp lệ.'); }
    if ($action !== 'accept') { $reply = validate_reflection_text($reply); }
    [$from, $to] = $transitions[$action];
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT Id, UserId, Status, Version FROM Reflections WHERE Id = ? AND ' . STAFF_REFLECTION_SCOPE . ' FOR UPDATE');
        $q->execute([$id, $staffId]);
        $reflection = $q->fetch();
        if (!$reflection) { throw new StaffReflectionNotFound('Phản ánh không tồn tại hoặc bạn không có quyền truy cập.'); }
        if ($reflection['Status'] !== $from || (int) $reflection['Version'] !== $version) {
            throw new StaffWorkflowConflict('Trạng thái hoặc phiên bản phản ánh đã thay đổi. Nội dung chưa được gửi; vui lòng kiểm tra thông tin mới trước khi thao tác lại.');
        }
        $eventTypes = ['resolve' => 'Resolved', 'request_info' => 'RequestInfo', 'close' => 'Closed'];
        $titles = ['resolve' => 'Phản ánh đã được xử lý', 'request_info' => 'Cần bổ sung thông tin phản ánh', 'close' => 'Hồ sơ phản ánh đã đóng'];
        $responseId = null;
        if ($action === 'resolve') {
            $q = $pdo->prepare('INSERT INTO Responses (Content, ReflectionId, UserId) VALUES (?, ?, ?)');
            $q->execute([$reply, $id, $staffId]);
            $responseId = (int) $pdo->lastInsertId();
        }
        if ($action !== 'accept') { reflection_activity($pdo, $id, $staffId, $eventTypes[$action], $reply, $version + 1, $responseId); }
        $q = $pdo->prepare('UPDATE Reflections SET Status = ?, Version = Version + 1 WHERE Id = ? AND Status = ? AND Version = ? AND ' . STAFF_REFLECTION_SCOPE);
        $q->execute([$to, $id, $from, $version, $staffId]);
        if ($q->rowCount() !== 1) { throw new StaffWorkflowConflict('Trạng thái hoặc quyền truy cập vừa thay đổi.'); }
        if ($action !== 'accept') { notify_student($pdo, (int) $reflection['UserId'], $id, $to, $titles[$action], $reply); }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}

function student_supplement_reflection(PDO $pdo, int $id, int $studentId, string $content, int $version, ?array $upload = null): void
{
    $content = validate_reflection_text($content);
    $stored = null;
    $pdo->beginTransaction();
    try {
        $scope = "EXISTS (SELECT 1 FROM Users actor WHERE actor.Id = ? AND actor.Role = 'Student' AND actor.IsActive = 1)";
        $q = $pdo->prepare('SELECT Status, Version FROM Reflections WHERE Id = ? AND UserId = ? AND ' . $scope . ' FOR UPDATE');
        $q->execute([$id, $studentId, $studentId]);
        $reflection = $q->fetch();
        if (!$reflection) { throw new StaffReflectionNotFound('Phản ánh không tồn tại hoặc bạn không có quyền xem.'); }
        if ($reflection['Status'] !== 'NeedsInfo' || (int) $reflection['Version'] !== $version) {
            throw new StaffWorkflowConflict('Phản ánh không còn chờ bổ sung hoặc biểu mẫu đã cũ. Nội dung chưa được gửi; vui lòng kiểm tra lại.');
        }
        $stored = attachment_store($pdo, $id, $upload);
        reflection_activity($pdo, $id, $studentId, 'Supplement', $content, $version + 1);
        $q = $pdo->prepare("UPDATE Reflections SET Status = 'Pending', Version = Version + 1 WHERE Id = ? AND UserId = ? AND Status = 'NeedsInfo' AND Version = ? AND " . $scope);
        $q->execute([$id, $studentId, $version, $studentId]);
        if ($q->rowCount() !== 1) { throw new StaffWorkflowConflict('Trạng thái hoặc quyền truy cập vừa thay đổi.'); }
        notify_staff($pdo, $id, $content);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        attachment_remove_file($stored);
        throw $exception;
    }
}
