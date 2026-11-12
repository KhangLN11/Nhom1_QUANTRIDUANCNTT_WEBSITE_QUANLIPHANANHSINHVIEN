<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('Staff');
require __DIR__ . '/includes/staff-layout.php';
require __DIR__ . '/includes/staff-workflow.php';
$id = reflection_id($_GET['id'] ?? null);
if ($id === null) {
    staff_error_page(404, 'Phản ánh không tồn tại hoặc bạn không có quyền xem.', $user);
}
$reply = '';
$error = '';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Phương thức không được hỗ trợ.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply = is_string($_POST['reply'] ?? null) ? trim($_POST['reply']) : '';
    if (!csrf_valid()) {
        http_response_code(403);
        $error = 'Phiên làm việc không hợp lệ. Vui lòng kiểm tra nội dung và gửi lại.';
    } else {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        try {
            // Không nhận UserId cán bộ hoặc trạng thái đích từ dữ liệu POST.
            $version = reflection_version($_POST['version'] ?? null);
            if ($version === null) { throw new InvalidArgumentException('Thiếu hoặc sai phiên bản biểu mẫu. Vui lòng tải lại trang.'); }
            staff_process_reflection(db(), $id, (int) $user['Id'], $action, $reply, $version);
            $messages = ['accept' => 'Tiếp nhận phản ánh thành công. Bạn có thể nhập câu trả lời bên dưới.', 'resolve' => 'Đã gửi câu trả lời và đánh dấu phản ánh đã xử lý.', 'request_info' => 'Đã gửi yêu cầu bổ sung và thông báo cho sinh viên.', 'close' => 'Đã đóng hồ sơ và thông báo cho sinh viên.'];
            $_SESSION['staff_success'] = ['id' => $id, 'message' => $messages[$action]];
            redirect('staff-reflection.php?id=' . $id);
        } catch (StaffReflectionNotFound $exception) {
            staff_error_page(404, $exception->getMessage(), $user);
        } catch (StaffWorkflowConflict $exception) {
            http_response_code(409);
            $error = $exception->getMessage();
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log((string) $exception);
            http_response_code(503);
            $error = 'Chưa lưu được thay đổi. Hệ thống đã hoàn tác thao tác; nội dung trả lời vẫn được giữ lại để bạn thử lại.';
        }
    }
}
try {
    $query = db()->prepare('SELECT r.*, c.Name AS CategoryName, u.FullName AS SenderName, u.Email AS SenderEmail, u.StudentCode FROM Reflections r JOIN Categories c ON c.Id = r.CategoryId JOIN Users u ON u.Id = r.UserId WHERE r.Id = ? AND ' . STAFF_REFLECTION_SCOPE);
    $query->execute([$id, (int) $user['Id']]);
    $reflection = $query->fetch();
    if (!$reflection) {
        staff_error_page(404, 'Phản ánh không tồn tại hoặc bạn không có quyền xem.', $user);
    }
    $history = reflection_history(db(), $id, false);
} catch (PDOException $exception) {
    error_log((string) $exception);
    staff_error_page(503, 'Vui lòng thử lại sau hoặc liên hệ quản trị hệ thống.', $user);
}
$flash = $_SESSION['staff_success'] ?? null;
$success = is_array($flash) && $flash['id'] === $id ? $flash['message'] : '';
if ($success) {
    unset($_SESSION['staff_success']);
}
staff_header('Chi tiết & xử lý phản ánh');
?>
<div class="page-heading"><div><span class="section-tag">PHẢN ÁNH #<?= $id ?></span><h1>Chi tiết & xử lý</h1></div><a class="button secondary" href="staff.php">← Danh sách tiếp nhận</a></div>
<?php if ($success): ?><div class="success-message" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="staff-detail-grid"><div>
<article class="info-card reflection-detail"><div class="detail-heading"><h2><?= e($reflection['Title']) ?></h2><?php reflection_status($reflection['Status']); ?></div><dl class="detail-meta"><div><dt>Người gửi</dt><dd><?= e($reflection['SenderName']) ?><?= $reflection['StudentCode'] ? ' · ' . e($reflection['StudentCode']) : '' ?></dd></div><div><dt>Email</dt><dd><?= e($reflection['SenderEmail']) ?></dd></div><div><dt>Danh mục</dt><dd><?= e($reflection['CategoryName']) ?></dd></div><div><dt>Ngày gửi</dt><dd><?= e(reflection_date($reflection['CreatedAt'])) ?></dd></div></dl><h3>Nội dung phản ánh</h3><div class="plain-text"><?= e($reflection['Content']) ?></div></article>
<?php render_reflection_attachments(db(), $id); render_reflection_history($history); ?>
</div>
<aside class="info-card staff-processing"><span class="section-tag">XỬ LÝ PHẢN ÁNH</span><h2>Tiếp nhận & phản hồi</h2>
<?php if ($reflection['Status'] === 'Pending'): ?>
<p>Tiếp nhận phản ánh để bắt đầu hoặc tiếp tục xử lý sau khi sinh viên bổ sung.</p><form action="staff-reflection.php?id=<?= $id ?>" method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="version" value="<?= (int) $reflection['Version'] ?>"><input type="hidden" name="action" value="accept"><button class="button primary" type="submit">Tiếp nhận</button></form>
<?php elseif ($reflection['Status'] === 'InProgress'): ?>
<form action="staff-reflection.php?id=<?= $id ?>" method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="version" value="<?= (int) $reflection['Version'] ?>"><label for="reply">Nội dung trả lời hoặc lý do bổ sung <span class="required">*</span></label><textarea id="reply" name="reply" rows="9" maxlength="10000" required aria-describedby="reply-help"><?= e($reply) ?></textarea><p class="field-help" id="reply-help">Tối đa 10.000 ký tự. Nội dung được lưu trong lịch sử và thông báo cho sinh viên.</p><div class="workflow-actions"><button class="button primary staff-resolve" type="submit" name="action" value="resolve">Gửi trả lời và đánh dấu đã xử lý</button><button class="button secondary" type="submit" name="action" value="request_info">Yêu cầu bổ sung thông tin</button></div></form>
<?php elseif ($reflection['Status'] === 'NeedsInfo'): ?><p>Đang chờ sinh viên bổ sung thông tin. Sau khi sinh viên gửi, phản ánh trở về Chờ tiếp nhận.</p>
<?php elseif ($reflection['Status'] === 'Resolved'): ?>
<p class="staff-complete">✓ Phản ánh đã xử lý.</p><form action="staff-reflection.php?id=<?= $id ?>" method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="version" value="<?= (int) $reflection['Version'] ?>"><input type="hidden" name="action" value="close"><label for="reply">Lý do kết thúc hồ sơ <span class="required">*</span></label><textarea id="reply" name="reply" rows="5" maxlength="10000" required><?= e($reply) ?></textarea><p class="field-help">Chỉ đóng hồ sơ đã xử lý. Lý do sẽ được lưu và thông báo cho sinh viên.</p><button class="button primary" type="submit">Đóng hồ sơ</button></form>
<?php else: ?><p class="muted">Hồ sơ đã đóng. Bạn vẫn có thể xem nội dung gốc và toàn bộ lịch sử trao đổi.</p><?php endif; ?>
<?php if ($reply !== '' && $error && !in_array($reflection['Status'], ['InProgress', 'Resolved'], true)): ?><div class="staff-unsent"><h3>Nội dung chưa được gửi</h3><div class="plain-text"><?= e($reply) ?></div></div><?php endif; ?>
</aside></div>
<?php staff_footer($user); ?>
