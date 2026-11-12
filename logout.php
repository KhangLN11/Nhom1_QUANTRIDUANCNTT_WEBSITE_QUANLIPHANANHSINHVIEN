<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Vui lòng dùng nút Đăng xuất trên trang tài khoản.');
}
if (!csrf_valid()) {
    http_response_code(403);
    exit('Phiên làm việc không hợp lệ. Vui lòng tải lại trang.');
}
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 42000,
    'path' => $params['path'],
    'domain' => $params['domain'],
    'secure' => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => $params['samesite'],
]);
session_destroy();
redirect('login.php');
