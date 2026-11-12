<?php
require __DIR__ . '/includes/bootstrap.php';
$error = '';
$email = '';
try {
    $user = current_user();
    if ($user) {
        redirect(role_home($user['Role']));
    }
} catch (PDOException $exception) {
    error_log((string) $exception);
    $error = 'Chưa kết nối được cơ sở dữ liệu. Vui lòng kiểm tra MySQL và cấu hình kết nối.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $attempts = (int) ($_SESSION['login_attempts'] ?? 0);
    $lastAttempt = (int) ($_SESSION['last_attempt'] ?? 0);
    if (time() - $lastAttempt > 300) {
        $attempts = 0;
        $_SESSION['login_attempts'] = 0;
    }
    if (!csrf_valid()) {
        $error = 'Phiên đăng nhập đã hết hạn. Vui lòng tải lại trang và thử lại.';
    } elseif ($attempts >= 5) {
        $error = 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau 5 phút.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100 || $password === '' || strlen($password) > 4096) {
        $error = 'Vui lòng nhập email hợp lệ và mật khẩu.';
    } else {
        try {
            $query = db()->prepare('SELECT Id, PasswordHash, Role, IsActive FROM Users WHERE Email = ? LIMIT 1');
            $query->execute([$email]);
            $account = $query->fetch();
            // Hash dự phòng để vẫn kiểm tra mật khẩu khi email không tồn tại.
            $hash = $account ? $account['PasswordHash'] : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $validPassword = password_verify($password, $hash);
            if (!$account || !$validPassword || !(bool) $account['IsActive'] || !in_array($account['Role'], ['Student', 'Staff'], true)) {
                $_SESSION['login_attempts'] = $attempts + 1;
                $_SESSION['last_attempt'] = time();
                $error = 'Email hoặc mật khẩu không đúng, hoặc tài khoản chưa được kích hoạt.';
            } else {
                session_regenerate_id(true);
                $_SESSION = ['user_id' => (int) $account['Id'], 'csrf' => bin2hex(random_bytes(32))];
                redirect(role_home($account['Role']));
            }
        } catch (PDOException $exception) {
            error_log((string) $exception);
            $error = 'Chưa kết nối được cơ sở dữ liệu. Vui lòng kiểm tra MySQL và cấu hình kết nối.';
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập · CampusVoice</title>
    <link rel="stylesheet" href="assets/style.css">
<link rel="stylesheet" href="assets/ui.css"></head>
<body>
<div class="shell">
    <header class="topbar"><a class="brand" href="index.php">campusvoice<span>•</span></a><span class="top-caption">Lắng nghe · Kết nối · Thay đổi</span><span class="university">ĐẠI HỌC BÌNH DƯƠNG</span></header>
    <main class="login-layout">
        <section class="welcome" aria-labelledby="welcome-title">
            <span class="eyebrow">CÙNG XÂY DỰNG MÔI TRƯỜNG HỌC TẬP TỐT HƠN</span>
            <h1 id="welcome-title">Tiếng nói của bạn.<br><span>Thay đổi của chúng ta.</span></h1>
            <p>Chia sẻ ý kiến hôm nay — tạo nên thay đổi ngày mai.</p>
            <div class="campus-art" role="img" aria-label="Minh họa trường Đại học Bình Dương với tông màu đỏ rượu"></div>
            <div class="welcome-note"><span class="note-icon" aria-hidden="true">↗</span><div><strong>Ý kiến nhỏ, thay đổi lớn.</strong><br><span>Cùng làm trường mình tốt hơn.</span></div></div>
        </section>
        <section class="login-card" aria-labelledby="login-title">
            <span class="section-tag">CHÀO MỪNG BẠN TRỞ LẠI</span>
            <h2 id="login-title">Đăng nhập</h2>
            <p class="muted">Kết nối với CampusVoice bằng tài khoản của bạn.</p>
            <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="login.php">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="username" placeholder="ban@bdu.edu.vn" maxlength="100" required value="<?= e($email) ?>" autofocus>
                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu của bạn" required maxlength="4096">
                <p class="form-hint">Dành cho sinh viên và cán bộ nhà trường.</p>
                <button class="button primary" type="submit">Đăng nhập <span aria-hidden="true">↗</span></button>
            </form>
            <div class="secure-note"><span aria-hidden="true">↗</span> Ý kiến được lắng nghe. Thông tin được bảo vệ.</div>
            <p class="support">Chưa có tài khoản hoặc quên mật khẩu?<br>Vui lòng liên hệ cán bộ quản lý để được hỗ trợ.</p>
        </section>
    </main>
    <footer><span>© <?= date('Y') ?> CampusVoice</span><span>Không gian kết nối sinh viên và nhà trường.</span></footer>
</div>
</body>
</html>
