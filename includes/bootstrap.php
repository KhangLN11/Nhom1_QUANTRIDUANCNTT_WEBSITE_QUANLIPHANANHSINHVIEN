<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $config = require __DIR__ . '/../config/database.php';
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['name']),
            $config['user'], $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
    }
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path, true, 303);
    exit;
}

function role_home(string $role): string
{
    return $role === 'Staff' ? 'staff.php' : 'student.php';
}

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    session_name('campusvoice_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function csrf_valid(): bool
{
    return isset($_POST['csrf']) && is_string($_POST['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $query = db()->prepare('SELECT Id, FullName, Email, Role, StudentCode FROM Users WHERE Id = ? AND IsActive = 1');
    $query->execute([$_SESSION['user_id']]);
    $user = $query->fetch();
    if (!$user || !in_array($user['Role'], ['Student', 'Staff'], true)) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

function require_user(): array
{
    try { $user = current_user(); }
    catch (PDOException $exception) {
        error_log((string) $exception);
        http_response_code(503);
        exit('Chưa kết nối được cơ sở dữ liệu. Vui lòng kiểm tra MySQL và config/database.php.');
    }
    if (!$user) { redirect('login.php'); }
    return $user;
}

function require_role(string $role): array
{
    $user = require_user();
    if ($user['Role'] !== $role) { redirect(role_home($user['Role'])); }
    return $user;
}

require_once __DIR__ . '/notifications.php';
