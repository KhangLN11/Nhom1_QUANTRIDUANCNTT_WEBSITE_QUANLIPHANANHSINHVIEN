<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('Student');
require __DIR__ . '/includes/student-layout.php';
require_once __DIR__ . '/includes/staff-workflow.php';
$id = reflection_id($_GET['id'] ?? null);
$reflection = false;
$history = [];
$content = '';
$error = '';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST'); http_response_code(405); exit('Phương thức không được hỗ trợ.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id !== null) {
    $content = is_string($_POST['content'] ?? null) ? trim($_POST['content']) : '';
    if (attachment_post_too_large()) { http_response_code(413); $error = 'Gói tải lên vượt giới hạn post_max_size của PHP.'; }
    elseif (!csrf_valid()) { http_response_code(403); $error = 'Phiên làm việc không hợp lệ. Vui lòng kiểm tra nội dung và gửi lại.'; }
    else {
        try {
            if (($_POST['action'] ?? null) !== 'supplement') { throw new InvalidArgumentException('Thao tác không hợp lệ.'); }
            $version = reflection_version($_POST['version'] ?? null);
            if ($version === null) { throw new InvalidArgumentException('Thiếu hoặc sai phiên bản biểu mẫu. Vui lòng tải lại trang.'); }
            $upload = validate_attachment_upload($_FILES['attachment'] ?? null);
            student_supplement_reflection(db(), $id, (int) $user['Id'], $content, $version, $upload);
            $_SESSION['supplement_success'] = ['id' => $id, 'message' => 'Đã gửi nội dung bổ sung. Phản ánh đang chờ cán bộ tiếp nhận lại.'];
            redirect('reflection.php?id=' . $id);
        } catch (StaffReflectionNotFound $exception) { $id = null; }
        catch (StaffWorkflowConflict $exception) { http_response_code(409); $error = $exception->getMessage(); }
        catch (InvalidArgumentException $exception) { http_response_code(422); $error = $exception->getMessage(); }
        catch (PDOException $exception) { error_log((string) $exception); http_response_code(503); $error = 'Chưa lưu được phần bổ sung. Hệ thống đã hoàn tác; vui lòng thử lại.'; }
        catch (RuntimeException $exception) { error_log((string) $exception); http_response_code(503); $error = 'Chưa lưu được tệp hoặc phần bổ sung. Vui lòng thử lại.'; }
    }
}
try {
    if ($id !== null) {
        $query = db()->prepare('SELECT r.Id, r.Title, r.Content, r.Status, r.Version, r.CreatedAt, c.Name AS CategoryName FROM Reflections r JOIN Categories c ON c.Id = r.CategoryId WHERE r.Id = ? AND r.UserId = ?');
        $query->execute([$id, (int) $user['Id']]);
        $reflection = $query->fetch();
    }
    if ($reflection) {
        $history = reflection_history(db(), $id, true);
    }
} catch (PDOException $exception) {
    reflection_unavailable($exception);
}
if (!$reflection) {
    // Không phân biệt phản ánh không tồn tại và phản ánh của người khác.
    http_response_code(404);
    student_header('Không tìm thấy phản ánh');
    ?><section class="info-card empty-state"><h1>Không tìm thấy phản ánh</h1><p class="muted">Phản ánh không tồn tại hoặc bạn không có quyền xem.</p><a class="button primary compact" href="reflections.php">Về phản ánh của tôi</a></section><?php
    student_footer();
    exit;
}
$flash = $_SESSION['supplement_success'] ?? null;
$success = is_array($flash) && $flash['id'] === $id ? $flash['message'] : '';
if ($success) { unset($_SESSION['supplement_success']); }
student_header('Chi tiết phản ánh');
?>
<div class="page-heading"><div><span class="section-tag">PHẢN ÁNH #<?= (int) $reflection['Id'] ?></span><h1>Chi tiết phản ánh</h1></div><a class="button secondary" href="reflections.php">← Phản ánh của tôi</a></div>
<?php if ($success): ?><div class="success-message" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
<article class="info-card reflection-detail">
    <div class="detail-heading"><h2><?= e($reflection['Title']) ?></h2><?php reflection_status($reflection['Status']); ?></div>
    <dl class="detail-meta"><div><dt>Danh mục</dt><dd><?= e($reflection['CategoryName']) ?></dd></div><div><dt>Ngày gửi</dt><dd><time datetime="<?= e(str_replace(' ', 'T', $reflection['CreatedAt'])) ?>"><?= e(reflection_date($reflection['CreatedAt'])) ?></time></dd></div></dl>
    <h3>Nội dung phản ánh</h3><div class="plain-text"><?= e($reflection['Content']) ?></div>
</article>
<?php render_reflection_attachments(db(), $id); render_reflection_history($history); ?>
<?php if ($reflection['Status'] === 'NeedsInfo'): ?>
<section class="info-card supplement-form"><span class="section-tag">BỔ SUNG THEO YÊU CẦU</span><h2>Bổ sung nội dung phản ánh</h2><p class="muted">Đọc yêu cầu của cán bộ trong lịch sử ở trên. Nội dung gốc được giữ nguyên, phần bổ sung sẽ được lưu thành lượt riêng.</p><form method="post" action="reflection.php?id=<?= $id ?>" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="version" value="<?= (int) $reflection['Version'] ?>"><input type="hidden" name="action" value="supplement"><label for="content">Nội dung bổ sung <span class="required">*</span></label><textarea id="content" name="content" rows="7" maxlength="10000" required aria-describedby="supplement-help"><?= e($content) ?></textarea><p class="field-help" id="supplement-help">Tối đa 10.000 ký tự. Sau khi gửi, phản ánh trở về Chờ tiếp nhận.</p><?php attachment_upload_field(); ?><div class="form-actions"><button class="button primary compact" type="submit">Gửi nội dung bổ sung ↗</button></div></form></section>
<?php elseif ($error && $content !== ''): ?><section class="info-card supplement-form"><h2>Nội dung chưa được gửi</h2><div class="plain-text unsent-text"><?= e($content) ?></div></section><?php endif; ?>
<?php student_footer(); ?>
