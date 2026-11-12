<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('Student');
require __DIR__ . '/includes/student-layout.php';
try {
    $query = db()->prepare('SELECT r.Id, r.Title, r.CreatedAt, r.Status, c.Name AS CategoryName FROM Reflections r JOIN Categories c ON c.Id = r.CategoryId WHERE r.UserId = ? ORDER BY r.CreatedAt DESC, r.Id DESC');
    $query->execute([(int) $user['Id']]);
    $reflections = $query->fetchAll();
} catch (PDOException $exception) {
    reflection_unavailable($exception);
}
$success = $_SESSION['reflection_success'] ?? '';
unset($_SESSION['reflection_success']);
student_header('Phản ánh của tôi');
?>
<div class="page-heading"><div><span class="section-tag">THEO DÕI & KẾT NỐI</span><h1>Phản ánh của tôi</h1><p class="muted">Những ý kiến bạn đã gửi tới nhà trường, mới nhất trước.</p></div><a class="button primary compact" href="reflection-create.php">Gửi phản ánh ↗</a></div>
<?php if ($success): ?><div class="success-message" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if (!$reflections): ?>
<section class="info-card empty-state"><span class="empty-symbol" aria-hidden="true">✎</span><h2>Bạn chưa gửi phản ánh nào</h2><p class="muted">Có điều gì cần nhà trường hỗ trợ? Chọn “Gửi phản ánh”, nhập tiêu đề, danh mục và nội dung để chia sẻ ý kiến đầu tiên.</p><a class="button primary compact" href="reflection-create.php">Gửi phản ánh đầu tiên ↗</a></section>
<?php else: ?>
<section class="info-card list-card" aria-label="Danh sách phản ánh">
    <div class="list-summary"><?= count($reflections) ?> phản ánh đã gửi</div>
    <p class="table-hint">Cuộn ngang hoặc dùng phím mũi tên để xem đầy đủ bảng.</p><div class="table-scroll" tabindex="0" role="region" aria-label="Danh sách phản ánh, có thể cuộn ngang"><table class="reflection-table"><caption class="sr-only">Các phản ánh của sinh viên đang đăng nhập</caption><thead><tr><th scope="col">Tiêu đề</th><th scope="col">Danh mục</th><th scope="col">Ngày gửi</th><th scope="col">Trạng thái</th><th scope="col">Chi tiết</th></tr></thead><tbody>
    <?php foreach ($reflections as $reflection): ?>
        <tr><td class="reflection-title"><?= e($reflection['Title']) ?></td><td><?= e($reflection['CategoryName']) ?></td><td class="date-cell"><time datetime="<?= e(str_replace(' ', 'T', $reflection['CreatedAt'])) ?>"><?= e(reflection_date($reflection['CreatedAt'])) ?></time></td><td><?php reflection_status($reflection['Status']); ?></td><td><a class="detail-link" href="reflection.php?id=<?= (int) $reflection['Id'] ?>" aria-label="<?= e('Xem chi tiết: ' . $reflection['Title']) ?>">Xem chi tiết ↗</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
<?php endif; ?>
<?php student_footer(); ?>
