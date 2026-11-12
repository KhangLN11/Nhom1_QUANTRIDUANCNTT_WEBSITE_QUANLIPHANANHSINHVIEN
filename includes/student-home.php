<?php
if (!isset($user) || $user['Role'] !== 'Student') {
    http_response_code(404);
    exit;
}
function home_icon(string $name): void
{
    $paths = [
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 21h4M12 2V1"/>',
        'user' => '<circle cx="12" cy="7" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3Z"/>',
        'logout' => '<path d="M9 3H3v18h6M10 12h12m-5-5 5 5-5 5"/>',
        'edit' => '<path d="M11 4H3v17h17v-8M16 3l5 5M9 15l1-5L19 1l5 5-9 9Z"/>',
        'document' => '<path d="M5 2h9l5 5v15H5ZM14 2v6h5M8 12h8M8 16h8M8 19h5"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 5v7l5 3"/>',
        'book' => '<path d="M12 5C9 2 5 2 1 3v17c4-1 8-1 11 2 3-3 7-3 11-2V3c-4-1-8-1-11 2Zm0 0v17"/>',
        'arrow' => '<path d="M3 12h17m-7-7 7 7-7 7"/>',
        'up-right' => '<path d="M5 19 19 5M7 5h12v12"/>',
        'down' => '<path d="M12 3v18m-6-6 6 6 6-6"/>',
        'megaphone' => '<path d="m3 9 17-6v18L3 15ZM7 17l1 5h4l-2-6M23 9v6"/>',
    ];
    ?><svg class="home-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?= $paths[$name] ?? '' ?></svg><?php
}
$statusLabels = ['Pending' => 'Chờ tiếp nhận', 'InProgress' => 'Đang xử lý', 'NeedsInfo' => 'Cần bổ sung', 'Resolved' => 'Đã xử lý', 'Closed' => 'Đã đóng'];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trang chủ sinh viên · CampusVoice</title>
    <link rel="stylesheet" href="assets/student-home.css">
    <link rel="stylesheet" href="assets/notifications.css">
<link rel="stylesheet" href="assets/ui.css"><script src="assets/ui.js" defer></script></head>
<body class="student-home">
<div class="home-shell">
    <header class="home-header" data-site-header>
        <a class="home-brand" href="student.php" aria-label="CampusVoice — Trang chủ">campusvoice</a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="home-nav"><span class="nav-toggle-label">Menu điều hướng</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
        <nav class="home-nav" id="home-nav" data-site-nav aria-label="Điều hướng chính">
            <a class="is-current" href="student.php" aria-current="page">Trang chủ</a>
            <a href="reflections.php">Phản ánh của tôi</a>
            <a href="#home-guide">Hướng dẫn</a>
        </nav>
        <div class="home-header-actions">
            <?php notification_bell($user, 'home-icon-button notification-bell'); ?>
            <a class="home-account" href="#student-account" aria-label="Tài khoản"><?php home_icon('user'); ?><span>Tài khoản</span></a>
            <form action="logout.php" method="post">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <button class="home-icon-button" type="submit" aria-label="Đăng xuất" title="Đăng xuất"><?php home_icon('logout'); ?></button>
            </form>
        </div>
    </header>
    <main>
        <section class="home-hero" aria-labelledby="home-title">
            <div class="home-hero-art" aria-hidden="true"></div>
            <div class="home-hero-content">
                <p class="home-eyebrow">CÙNG XÂY DỰNG MÔI TRƯỜNG HỌC TẬP TỐT HƠN</p>
                <h1 id="home-title">Tiếng nói của bạn.<br>Thay đổi của chúng ta.</h1>
                <p class="home-hero-subtitle">Chia sẻ ý kiến hôm nay — tạo nên thay đổi ngày mai.</p>
                <div class="home-hero-buttons">
                    <a class="home-button hero-send" href="reflection-create.php">Gửi phản ánh ngay <?php home_icon('up-right'); ?></a>
                    <a class="home-button hero-explore" href="#student-area">Khám phá bên dưới <?php home_icon('down'); ?></a>
                </div>
            </div>
            <div class="home-hero-note"><?php home_icon('megaphone'); ?><div><strong>Ý kiến nhỏ, thay đổi lớn.</strong><span>Cùng làm trường mình tốt hơn.</span></div></div>
        </section>
        <section class="home-student-area" id="student-area" aria-label="Không gian sinh viên">
            <aside class="home-role"><div class="home-role-avatar" aria-hidden="true">SV</div><h2>Sinh viên</h2><p>Tài khoản học tập</p></aside>
            <div class="home-workspace">
                <div class="home-panels">
                    <section class="home-student-card" id="student-account" aria-labelledby="student-info-title">
                        <p class="home-section-tag" id="student-info-title">THÔNG TIN SINH VIÊN</p>
                        <h2>Xin chào, bạn!</h2><p class="home-card-subtitle">Sẵn sàng chia sẻ điều bạn quan tâm?</p>
                        <dl class="home-profile">
                            <div><dt>Họ và tên</dt><dd><?= e($user['FullName']) ?></dd></div>
                            <div><dt>Mã sinh viên</dt><dd><?= e($user['StudentCode'] ?: 'Chưa cập nhật') ?></dd></div>
                        </dl>
                        <p class="home-profile-note">Thông tin từ tài khoản của bạn.</p>
                    </section>
                    <section class="home-recent-card" aria-labelledby="recent-title">
                        <h2 id="recent-title">Phản hồi gần đây</h2>
                        <?php if ($recentError): ?><div class="home-empty" role="status"><p>Chưa tải được phản ánh gần đây.</p><a href="reflections.php">Thử mở danh sách phản ánh →</a></div>
                        <?php elseif (!$recentReflections): ?><div class="home-empty"><span class="home-empty-icon"><?php home_icon('document'); ?></span><h3>Bạn chưa gửi phản ánh nào</h3><p>Chia sẻ ý kiến đầu tiên để nhà trường có thể lắng nghe và hỗ trợ bạn.</p><a href="reflection-create.php">Gửi phản ánh ngay →</a></div>
                        <?php else: ?><div class="home-recent-list">
                            <?php foreach ($recentReflections as $reflection): ?>
                                <a class="home-recent-item" href="reflection.php?id=<?= (int) $reflection['Id'] ?>">
                                    <div><h3><?= e($reflection['Title']) ?></h3><p><span class="home-status-dot" aria-hidden="true"></span><?= e($statusLabels[$reflection['Status']] ?? 'Chưa xác định') ?><span aria-hidden="true"> · </span><time datetime="<?= e(str_replace(' ', 'T', $reflection['CreatedAt'])) ?>"><?= e((new DateTimeImmutable($reflection['CreatedAt']))->format('d/m/Y')) ?></time></p></div>
                                    <?php home_icon('arrow'); ?>
                                </a>
                            <?php endforeach; ?>
                        </div><?php endif; ?>
                    </section>
                </div>
                <nav class="home-quick-actions" aria-label="Thao tác sinh viên">
                    <a class="is-primary" href="reflection-create.php"><?php home_icon('edit'); ?><span>Gửi phản ánh</span></a>
                    <a href="reflections.php"><?php home_icon('document'); ?><span>Phản ánh của tôi</span></a>
                    <a href="reflections.php"><?php home_icon('clock'); ?><span>Lịch sử phản hồi</span></a>
                    <a href="#home-guide"><?php home_icon('book'); ?><span>Hướng dẫn</span></a>
                </nav>
                <p class="home-data-note">Dữ liệu được hiển thị từ tài khoản đang đăng nhập.</p>
            </div>
        </section>
        <details class="home-guide" id="home-guide">
            <summary>Hướng dẫn sử dụng CampusVoice</summary>
            <div><p><strong>Gửi phản ánh:</strong> Nhập tiêu đề, chọn danh mục và mô tả vấn đề, sau đó chọn “Gửi phản ánh”.</p><p><strong>Theo dõi:</strong> Mở “Phản ánh của tôi” và chọn một phản ánh để xem trạng thái, nội dung và câu trả lời của cán bộ.</p><p>“Lịch sử phản hồi” hiện dẫn tới danh sách phản ánh; chưa có trang lịch sử riêng. Mở biểu tượng chuông để xem thông báo và phản ánh liên quan.</p></div>
        </details>
    </main>
</div>
</body>
</html>
