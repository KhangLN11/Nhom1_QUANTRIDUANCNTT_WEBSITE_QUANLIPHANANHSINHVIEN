<?php
function notification_unread_count(int $userId): int
{
    $q = db()->prepare('SELECT COUNT(*) FROM Notifications WHERE UserId = ? AND IsRead = 0');
    $q->execute([$userId]);
    return (int) $q->fetchColumn();
}

function notification_bell(array $user, string $class = 'notification-bell'): void
{
    try { $count = notification_unread_count((int) $user['Id']); }
    catch (PDOException $exception) { error_log((string) $exception); $count = 0; }
    ?><a class="<?= e($class) ?>" href="notifications.php" aria-label="<?= e('Thông báo, ' . $count . ' chưa đọc') ?>" title="Thông báo"><svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4M12 2V1"/></svg><span class="notification-count"><?= $count > 99 ? '99+' : $count ?></span></a><?php
}

function notify_student(PDO $pdo, int $owner, int $reflectionId, string $type, string $title, string $content): void
{
    $q = $pdo->prepare('INSERT INTO Notifications (Title, Content, Type, UserId, ReflectionId) VALUES (?, ?, ?, ?, ?)');
    $q->execute([$title, mb_substr($content, 0, 500, 'UTF-8'), $type, $owner, $reflectionId]);
}

function notify_staff(PDO $pdo, int $reflectionId, string $content): void
{
    // Scope hiện tại: mọi cán bộ Staff đang hoạt động, không có phân công riêng.
    $q = $pdo->prepare("INSERT INTO Notifications (Title, Content, Type, UserId, ReflectionId) SELECT ?, ?, ?, Id, ? FROM Users WHERE Role = 'Staff' AND IsActive = 1");
    $q->execute(['Sinh viên đã bổ sung thông tin', mb_substr($content, 0, 500, 'UTF-8'), 'Supplement', $reflectionId]);
}

function notification_open(PDO $pdo, int $notificationId, array $user): int
{
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT ReflectionId FROM Notifications WHERE Id = ? AND UserId = ? FOR UPDATE');
        $q->execute([$notificationId, (int) $user['Id']]);
        $id = $q->fetchColumn();
        if ($id === false) { throw new OutOfBoundsException('Không tìm thấy thông báo.'); }
        // Không tin URL, vai trò hoặc ID người dùng gửi lên từ biểu mẫu.
        $q = $pdo->prepare("SELECT r.Id FROM Reflections r JOIN Users actor ON actor.Id = ? AND actor.IsActive = 1 WHERE r.Id = ? AND (actor.Role = 'Staff' OR (actor.Role = 'Student' AND r.UserId = actor.Id))");
        $q->execute([(int) $user['Id'], (int) $id]);
        if (!$q->fetchColumn()) { throw new OutOfBoundsException('Không tìm thấy phản ánh hoặc bạn không có quyền xem.'); }
        $q = $pdo->prepare('UPDATE Notifications SET IsRead = 1, ReadAt = COALESCE(ReadAt, CURRENT_TIMESTAMP) WHERE Id = ? AND UserId = ? AND IsRead = 0');
        $q->execute([$notificationId, (int) $user['Id']]);
        $pdo->commit();
        return (int) $id;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}
