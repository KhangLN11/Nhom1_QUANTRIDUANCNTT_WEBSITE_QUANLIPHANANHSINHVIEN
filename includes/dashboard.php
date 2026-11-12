<?php
if (!isset($user)) {
    http_response_code(404);
    exit;
}
$isStaff = $user['Role'] === 'Staff';
$roleName = $isStaff ? 'Cán bộ' : 'Sinh viên';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($roleName) ?> · CampusVoice</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="shell">
    <header class="topbar">
        <a class="brand" href="index.php">campusvoice<span>•</span></a>
        <nav aria-label="Điều hướng chính"><a class="active" href="<?= e(role_home($user['Role'])) ?>" aria-current="page">Trang chủ</a><?php if (!$isStaff): ?><a href="reflections.php">Phản ánh của tôi</a><a href="reflection-create.php">Gửi phản ánh</a><?php endif; ?><a href="#account">Tài khoản</a></nav>
        <form action="logout.php" method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><button class="button logout" type="submit">Đăng xuất <span aria-hidden="true">↗</span></button></form>
    </header>
    <main>
        <section class="dashboard-hero">
            <span class="eyebrow">CÙNG XÂY DỰNG MÔI TRƯỜNG HỌC TẬP TỐT HƠN</span>
            <h1>Tiếng nói của bạn.<br>Thay đổi của chúng ta.</h1>
            <p><?= $isStaff ? 'Lắng nghe sinh viên, cùng xây dựng môi trường học tập tốt hơn.' : 'Chia sẻ ý kiến hôm nay — tạo nên thay đổi ngày mai.' ?></p>
            <span class="hero-pill">CampusVoice / <?= e($roleName) ?></span>
        </section>
        <section class="dashboard-grid" id="account">
            <div class="role-card"><div class="role-avatar"><?= $isStaff ? 'CB' : 'SV' ?></div><h2><?= e($roleName) ?></h2><p><?= $isStaff ? 'Tài khoản nhà trường' : 'Tài khoản học tập' ?></p></div>
            <article class="info-card"><span class="section-tag">THÔNG TIN <?= $isStaff ? 'CÁN BỘ' : 'SINH VIÊN' ?></span><h2>Xin chào, <?= e($user['FullName']) ?>!</h2><p class="muted">Chào mừng bạn đến với CampusVoice.</p><dl><div><dt>Họ và tên</dt><dd><?= e($user['FullName']) ?></dd></div><div><dt>Email</dt><dd><?= e($user['Email']) ?></dd></div><?php if (!$isStaff): ?><div><dt>Mã sinh viên</dt><dd><?= e($user['StudentCode'] ?: 'Chưa cập nhật') ?></dd></div><?php endif; ?><div><dt>Vai trò</dt><dd><?= e($roleName) ?></dd></div></dl></article>
            <article class="info-card next-step"><span class="section-tag">KHÔNG GIAN CỦA BẠN</span><h2><?= $isStaff ? 'Lắng nghe & đồng hành' : 'Mỗi ý kiến đều có giá trị' ?></h2>
                <?php if ($isStaff): ?><p>Đây là trang dành riêng cho cán bộ tiếp nhận và phản hồi ý kiến sinh viên.</p><div class="coming-soon"><strong>Chức năng sẽ được phát triển tiếp</strong><p>Tiếp nhận, phân loại và xử lý phản ánh.</p></div>
                <?php else: ?><p>Chia sẻ ý kiến và theo dõi câu trả lời từ nhà trường.</p><div class="home-reflection-actions"><a class="button primary compact" href="reflection-create.php">Gửi phản ánh ↗</a><a class="button secondary" href="reflections.php">Phản ánh của tôi</a></div><p class="muted">Mở từng phản ánh trong danh sách để xem nội dung, trạng thái và câu trả lời của cán bộ.</p><?php endif; ?>
            </article>
        </section>
    </main>
    <footer><span>© <?= date('Y') ?> CampusVoice</span><span>Ý kiến nhỏ, thay đổi lớn.</span></footer>
</div>
</body>
</html>
