<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('Student');
require __DIR__ . '/includes/student-layout.php';
require_once __DIR__ . '/includes/attachments.php';
$upload = null;
$title = '';
$content = '';
$categoryValue = '';
$errors = [];
try {
    $query = db()->prepare('SELECT Id, Name FROM Categories ORDER BY Name, Id');
    $query->execute();
    $categories = $query->fetchAll();
} catch (PDOException $exception) {
    reflection_unavailable($exception);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
    $content = is_string($_POST['content'] ?? null) ? trim($_POST['content']) : '';
    $categoryValue = is_string($_POST['category_id'] ?? null) ? $_POST['category_id'] : '';
    $categoryId = reflection_id($categoryValue);
    if (!csrf_valid()) {
        $errors['csrf'] = 'Phiên làm việc không hợp lệ. Vui lòng kiểm tra nội dung và gửi lại.';
    }
    if (!mb_check_encoding($title, 'UTF-8') || $title === '' || mb_strlen($title, 'UTF-8') > 200) {
        $errors['title'] = 'Tiêu đề phải có từ 1 đến 200 ký tự.';
    }
    if (!mb_check_encoding($content, 'UTF-8') || $content === '' || mb_strlen($content, 'UTF-8') > 10000) {
        $errors['content'] = 'Nội dung phải có từ 1 đến 10.000 ký tự.';
    }
    if ($categoryId === null || !in_array($categoryId, array_map('intval', array_column($categories, 'Id')), true)) {
        $errors['category_id'] = 'Vui lòng chọn một danh mục hợp lệ.';
    }
    if (attachment_post_too_large()) { $errors['upload'] = 'Gói tải lên vượt giới hạn post_max_size của PHP.'; }
    if (!$errors) {
        try { $upload = validate_attachment_upload($_FILES['attachment'] ?? null); }
        catch (InvalidArgumentException $exception) { $errors['upload'] = $exception->getMessage(); }
    }
    if (!$errors) {
        $stored = null;
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $insert = db()->prepare("INSERT INTO Reflections (Title, Content, Status, UserId, CategoryId) VALUES (?, ?, 'Pending', ?, ?)");
            // Không nhận UserId hoặc Status từ form.
            $insert->execute([$title, $content, (int) $user['Id'], $categoryId]);
            $stored = attachment_store($pdo, (int) $pdo->lastInsertId(), $upload);
            $pdo->commit();
            $_SESSION['reflection_success'] = 'Gửi phản ánh thành công. Nhà trường sẽ tiếp nhận ý kiến của bạn.';
            redirect('reflections.php');
        } catch (PDOException | RuntimeException $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            attachment_remove_file($stored);
            error_log((string) $exception);
            http_response_code(503);
            $errors['database'] = 'Chưa lưu được phản ánh. Vui lòng thử lại; nội dung bạn nhập vẫn được giữ bên dưới.';
        }
    } else {
        http_response_code(attachment_post_too_large() ? 413 : (isset($errors['csrf']) ? 403 : 422));
    }
}
student_header('Gửi phản ánh', 'create');
?>
<div class="page-heading"><div><span class="section-tag">TIẾNG NÓI SINH VIÊN</span><h1>Gửi phản ánh</h1><p class="muted">Chia sẻ điều bạn quan tâm để cùng xây dựng môi trường học tập tốt hơn.</p></div></div>
<div class="reflection-form-grid">
<section class="info-card">
    <?php if ($errors): ?>
        <div class="alert" role="alert"><strong>Phản ánh chưa được gửi.</strong><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if (!$categories): ?><div class="alert" role="alert">Chưa có danh mục tiếp nhận. Vui lòng liên hệ cán bộ quản lý và quay lại sau.</div><?php endif; ?>
    <form method="post" action="reflection-create.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <label for="title">Tiêu đề <span class="required">*</span></label>
        <input id="title" name="title" value="<?= e($title) ?>" maxlength="200" required placeholder="Ví dụ: Wi-Fi tại thư viện chưa ổn định" aria-describedby="title-help"<?= isset($errors['title']) ? ' aria-invalid="true"' : '' ?>>
        <p class="field-help" id="title-help">Tóm tắt vấn đề trong tối đa 200 ký tự.</p>
        <label for="category_id">Danh mục <span class="required">*</span></label>
        <select id="category_id" name="category_id" required<?= isset($errors['category_id']) ? ' aria-invalid="true"' : '' ?>>
            <option value="">Chọn danh mục phản ánh</option>
            <?php foreach ($categories as $category): ?><option value="<?= (int) $category['Id'] ?>"<?= $categoryValue === (string) $category['Id'] ? ' selected' : '' ?>><?= e($category['Name']) ?></option><?php endforeach; ?>
        </select>
        <label for="content">Nội dung <span class="required">*</span></label>
        <textarea id="content" name="content" rows="9" maxlength="10000" required placeholder="Mô tả vấn đề, địa điểm, thời gian và đề xuất của bạn…" aria-describedby="content-help"<?= isset($errors['content']) ? ' aria-invalid="true"' : '' ?>><?= e($content) ?></textarea>
        <p class="field-help" id="content-help">Tối đa 10.000 ký tự. Những mục có dấu * là bắt buộc.</p>
        <?php attachment_upload_field(); ?>
        <div class="form-actions"><button class="button primary compact" type="submit"<?= !$categories ? ' disabled' : '' ?>>Gửi phản ánh ↗</button><a class="button secondary" href="reflections.php">Về danh sách</a></div>
    </form>
</section>
<aside class="info-card form-guide"><span class="section-tag">CÙNG LẮNG NGHE</span><h2>Mỗi ý kiến đều có giá trị</h2><p>Hãy mô tả cụ thể và sử dụng ngôn ngữ tôn trọng để nhà trường có thể hỗ trợ bạn tốt hơn.</p><ol><li>Đặt tiêu đề ngắn gọn, rõ vấn đề.</li><li>Chọn danh mục phù hợp.</li><li>Nêu chi tiết và đề xuất cách cải thiện.</li></ol><p class="muted">Sau khi gửi, bạn có thể theo dõi trạng thái và câu trả lời trong “Phản ánh của tôi”.</p></aside>
</div>
<?php student_footer(); ?>
