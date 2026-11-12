<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('Student');
$recentReflections = [];
$recentError = false;
try {
    $query = db()->prepare('SELECT Id, Title, Status, CreatedAt FROM Reflections WHERE UserId = ? ORDER BY CreatedAt DESC, Id DESC LIMIT 3');
    $query->execute([(int) $user['Id']]);
    $recentReflections = $query->fetchAll();
} catch (PDOException $exception) {
    error_log((string) $exception);
    $recentError = true;
}
require __DIR__ . '/includes/student-home.php';
