<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_user();
require __DIR__ . ($user['Role'] === 'Staff' ? '/includes/staff-layout.php' : '/includes/student-layout.php');
require_once __DIR__ . '/includes/reflection-history.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) { http_response_code(403); $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'; }
    else {
        $id = reflection_id($_POST['notification_id'] ?? null);
        if ($id === null) { http_response_code(404); $error = 'Không tìm thấy thông báo.'; }
        else {
            try {
                $reflectionId = notification_open(db(), $id, $user);
                redirect(($user['Role'] === 'Staff' ? 'staff-reflection.php?id=' : 'reflection.php?id=') . $reflectionId);
            } catch (OutOfBoundsException $exception) { http_response_code(404); $error = $exception->getMessage(); }
            catch (PDOException $exception) { error_log((string) $exception); http_response_code(503); $error = 'Chưa mở được thông báo. Vui lòng thử lại.'; }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST'); http_response_code(405); exit('Phương thức không được hỗ trợ.');
}
$notifications = [];
try {
    $q = db()->prepare('SELECT Id, Title, Content, IsRead, CreatedAt FROM Notifications WHERE UserId = ? ORDER BY CreatedAt DESC, Id DESC');
    $q->execute([(int) $user['Id']]);
    $notifications = $q->fetchAll();
} catch (PDOException $exception) { error_log((string) $exception); http_response_code(503); $error = 'Chưa tải được danh sách thông báo.'; }
if ($user['Role'] === 'Staff') { staff_header('Thông báo'); } else { student_header('Thông báo', 'notifications'); }
?>
<div class="page-heading"><div><span class="section-tag">CẬP NHẬT TỪ CAMPUSVOICE</span><h1>Thông báo</h1><p class="muted">Mở phản ánh để xem nội dung và đánh dấu thông báo đã đọc.</p></div></div>
<?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (!$notifications && !$error): ?><section class="info-card empty-state"><h2>Bạn chưa có thông báo</h2><p class="muted">Các cập nhật về phản ánh sẽ được hiển thị tại đây.</p></section><?php endif; ?>
<div class="notification-list"><?php foreach ($notifications as $notification): ?><article class="info-card notification-item<?= $notification['IsRead'] ? '' : ' is-unread' ?>"><div><span class="section-tag"><?= $notification['IsRead'] ? 'ĐÃ ĐỌC' : 'CHƯA ĐỌC' ?></span><h2><?= e($notification['Title']) ?></h2><p class="plain-text"><?= e($notification['Content']) ?></p><time class="muted"><?= e(reflection_date($notification['CreatedAt'])) ?></time></div><form method="post" action="notifications.php"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="notification_id" value="<?= (int) $notification['Id'] ?>"><button class="button secondary" type="submit">Mở phản ánh ↗</button></form></article><?php endforeach; ?></div>
<?php if ($user['Role'] === 'Staff') { staff_footer($user); } else { student_footer(); } ?>
