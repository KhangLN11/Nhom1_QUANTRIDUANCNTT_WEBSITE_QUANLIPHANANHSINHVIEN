<?php
if (!isset($user) || $user['Role'] !== 'Staff') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/reflection-format.php';

function staff_header(string $title): void
{
    global $user;
    ?>
    <!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?> · CampusVoice</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/notifications.css"><link rel="stylesheet" href="assets/staff.css"><link rel="stylesheet" href="assets/ui.css"><script src="assets/ui.js" defer></script></head>
    <body class="staff-area"><div class="shell">
    <header class="topbar" data-site-header><a class="brand" href="staff.php">campusvoice<span>•</span></a><button class="nav-toggle" type="button" aria-expanded="false" aria-controls="staff-nav"><span class="nav-toggle-label">Menu điều hướng</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button><nav id="staff-nav" data-site-nav aria-label="Điều hướng cán bộ"><a class="active" href="staff.php">Danh sách tiếp nhận</a><a href="#staff-account">Tài khoản cán bộ</a></nav><?php notification_bell($user); ?><form action="logout.php" method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><button class="button logout" type="submit" aria-label="Đăng xuất" title="Đăng xuất"><span class="logout-label">Đăng xuất</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3H3v18h6M10 12h12m-5-5 5 5-5 5"/></svg></button></form></header>
    <main class="reflection-main">
    <?php
}

function staff_footer(array $user): void
{
    ?></main><footer id="staff-account"><span><?= e($user['FullName']) ?> · Cán bộ · <?= e($user['Email']) ?></span><span>CampusVoice — Lắng nghe & đồng hành.</span></footer></div></body></html><?php
}

function staff_error_page(int $status, string $message, array $user): void
{
    http_response_code($status);
    staff_header('Chưa thể mở phản ánh');
    ?><section class="info-card empty-state"><h1><?= $status === 404 ? 'Không tìm thấy phản ánh' : 'Tạm thời chưa tải được dữ liệu' ?></h1><p class="muted"><?= e($message) ?></p><a class="button secondary" href="staff.php">Về danh sách tiếp nhận</a></section><?php
    staff_footer($user);
    exit;
}
