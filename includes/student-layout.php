<?php
// Chỉ được nạp sau khi trang đã xác thực vai trò sinh viên.
if (!isset($user) || $user['Role'] !== 'Student') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/reflection-format.php';

function student_header(string $title, string $active = 'list'): void
{
    global $user;
    ?>
    <!doctype html>
    <html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · CampusVoice</title>
        <link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/notifications.css">
    <link rel="stylesheet" href="assets/ui.css"><script src="assets/ui.js" defer></script></head>
    <body><div class="shell">
    <header class="topbar" data-site-header>
        <a class="brand" href="student.php">campusvoice<span>•</span></a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="student-nav"><span class="nav-toggle-label">Menu điều hướng</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button><nav id="student-nav" data-site-nav aria-label="Điều hướng sinh viên">
            <a href="student.php">Trang chủ</a>
            <a href="reflections.php"<?= $active === 'list' ? ' class="active" aria-current="page"' : '' ?>>Phản ánh của tôi</a>
            <a href="reflection-create.php"<?= $active === 'create' ? ' class="active" aria-current="page"' : '' ?>>Gửi phản ánh</a>
        </nav>
        <?php notification_bell($user); ?>
        <form action="logout.php" method="post">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <button class="button logout" type="submit" aria-label="Đăng xuất" title="Đăng xuất"><span class="logout-label">Đăng xuất</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3H3v18h6M10 12h12m-5-5 5 5-5 5"/></svg></button>
        </form>
    </header>
    <main class="reflection-main">
    <a class="back-link" href="<?= $active === 'create' ? 'reflections.php' : 'student.php' ?>">← <?= $active === 'create' ? 'Phản ánh của tôi' : 'Trang chủ sinh viên' ?></a>
    <?php
}

function student_footer(): void
{
    ?>
    </main><footer><span>© <?= date('Y') ?> CampusVoice</span><span>Ý kiến nhỏ, thay đổi lớn.</span></footer>
    </div></body></html>
    <?php
}

function reflection_unavailable(PDOException $exception): void
{
    error_log((string) $exception);
    http_response_code(503);
    student_header('Tạm thời chưa tải được dữ liệu');
    ?><section class="info-card empty-state"><h1>Tạm thời chưa tải được dữ liệu</h1><p>Vui lòng thử lại sau hoặc liên hệ cán bộ quản lý.</p><a class="button secondary" href="reflections.php">Về danh sách</a></section><?php
    student_footer();
    exit;
}

