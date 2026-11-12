<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/bootstrap.php';
$accounts = [
    ['Nguyễn Minh Anh', 'student@campusvoice.test', 'Student', 'SV2026001', 'Student@123'],
    ['Lê Thu Hà', 'student2@campusvoice.test', 'Student', 'SV2026002', 'Student2@123'],
    ['Trần Hoài An', 'staff@campusvoice.test', 'Staff', null, 'Staff@123'],
];
try {
    $pdo = db();
    $pdo->beginTransaction();
    $exists = $pdo->prepare('SELECT Id FROM Users WHERE Email = ? OR (? IS NOT NULL AND StudentCode = ?)');
    $insert = $pdo->prepare('INSERT INTO Users (FullName, Email, PasswordHash, Role, StudentCode, IsActive) VALUES (?, ?, ?, ?, ?, 1)');
    $messages = [];
    foreach ($accounts as [$name, $email, $role, $code, $password]) {
        $exists->execute([$email, $code, $code]);
        if ($exists->fetch()) {
            $messages[] = "Bỏ qua $email: email hoặc mã sinh viên đã tồn tại; giữ nguyên tài khoản.";
            continue;
        }
        $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $code]);
        $messages[] = "Đã tạo $role: $email / $password";
    }
    $pdo->commit();
    echo implode(PHP_EOL, $messages) . PHP_EOL;
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Không tạo được tài khoản. Kiểm tra MySQL, cấu hình kết nối và import database/campusvoice.sql.\n");
    exit(1);
}
