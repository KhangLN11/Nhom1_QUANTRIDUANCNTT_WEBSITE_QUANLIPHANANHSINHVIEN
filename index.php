<?php
require __DIR__ . '/includes/bootstrap.php';
try {
    $user = current_user();
    redirect($user ? role_home($user['Role']) : 'login.php');
} catch (PDOException $exception) {
    error_log((string) $exception);
    redirect('login.php');
}
