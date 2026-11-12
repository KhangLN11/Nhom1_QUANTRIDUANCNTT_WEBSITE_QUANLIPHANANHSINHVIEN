<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('Staff');
require __DIR__ . '/includes/staff-layout.php';
require __DIR__ . '/includes/staff-query.php';
$labels = reflection_status_labels();
$status = $_GET['status'] ?? '';
$filterError = !is_string($status) || ($status !== '' && !isset($labels[$status]));
$reflections = [];
if ($filterError) {
    http_response_code(422);
    $status = '';
} else {
    try {
        $query = staff_reflection_query(db(), (int) $user['Id'], $status);
        $reflections = $query->fetchAll();
    } catch (PDOException $exception) {
        error_log((string) $exception);
        staff_error_page(503, 'Vui lòng thử lại sau hoặc liên hệ quản trị hệ thống.', $user);
    }
}
staff_header('Danh sách tiếp nhận');
?>
<section class="staff-intro"><span class="eyebrow">KHÔNG GIAN CÁN BỘ</span><h1>Tiếp nhận & đồng hành</h1><p>Lắng nghe ý kiến sinh viên, cùng xây dựng môi trường học tập tốt hơn.</p></section>
<div class="page-heading"><div><span class="section-tag">PHẢN ÁNH SINH VIÊN</span><h2>Danh sách tiếp nhận</h2><p class="muted">Phản ánh mới nhất được hiển thị trước.</p></div><span class="staff-scope-note">Phạm vi hiện tại: toàn trường</span></div>
<section class="info-card staff-filter"><form method="get" action="staff.php"><label for="status">Trạng thái</label><select id="status" name="status"><option value="">Tất cả trạng thái</option><?php foreach ($labels as $value => $label): ?><option value="<?= e($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="button primary compact" type="submit">Lọc phản ánh</button><a class="button secondary" href="staff.php">Bỏ lọc</a><?php if (!$filterError): ?><a class="button secondary" href="staff-export.php?<?= e(http_build_query(['status' => $status])) ?>">Xuất CSV</a><?php endif; ?></form></section>
<?php if ($filterError): ?><div class="alert" role="alert">Trạng thái lọc không hợp lệ. Vui lòng chọn một trạng thái trong danh sách.</div>
<?php elseif (!$reflections): ?><section class="info-card empty-state"><h2>Không có phản ánh phù hợp</h2><p class="muted">Chưa có phản ánh trong phạm vi và trạng thái đã chọn. Bạn có thể bỏ bộ lọc hoặc quay lại sau.</p><a class="button secondary" href="staff.php">Xem tất cả phản ánh</a></section>
<?php else: ?>
<section class="info-card list-card"><div class="list-summary"><?= count($reflections) ?> phản ánh<?= $status !== '' ? ' · ' . e($labels[$status]) : '' ?></div><p class="table-hint">Cuộn ngang hoặc dùng phím mũi tên để xem đầy đủ bảng.</p><div class="table-scroll" tabindex="0" role="region" aria-label="Danh sách phản ánh, có thể cuộn ngang"><table class="reflection-table"><caption class="sr-only">Phản ánh cán bộ được phép xem</caption><thead><tr><th scope="col">Tiêu đề</th><th scope="col">Danh mục</th><th scope="col">Người gửi</th><th scope="col">Ngày gửi</th><th scope="col">Trạng thái</th><th scope="col">Chi tiết</th></tr></thead><tbody>
<?php foreach ($reflections as $reflection): ?><tr><td class="reflection-title"><?= e($reflection['Title']) ?></td><td><?= e($reflection['CategoryName']) ?></td><td><?= e($reflection['SenderName']) ?><?php if ($reflection['StudentCode']): ?><small class="staff-student-code"><?= e($reflection['StudentCode']) ?></small><?php endif; ?></td><td class="date-cell"><?= e(reflection_date($reflection['CreatedAt'])) ?></td><td><?php reflection_status($reflection['Status']); ?></td><td><a class="detail-link" href="staff-reflection.php?id=<?= (int) $reflection['Id'] ?>" aria-label="<?= e('Xem chi tiết: ' . $reflection['Title']) ?>">Xem chi tiết ↗</a></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>
<?php staff_footer($user); ?>
