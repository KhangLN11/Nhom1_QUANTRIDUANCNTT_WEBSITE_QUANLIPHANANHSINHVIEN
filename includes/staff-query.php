<?php
require_once __DIR__ . '/staff-workflow.php';
function staff_reflection_query(PDO $pdo, int $staffId, $status): PDOStatement
{
    if (!is_string($status) || ($status !== '' && !isset(reflection_status_labels()[$status]))) {
        throw new InvalidArgumentException('Trạng thái lọc không hợp lệ.');
    }
    $sql = 'SELECT r.Id, r.Title, r.Content AS ReflectionContent, r.CreatedAt, r.Status, c.Name AS CategoryName, u.FullName AS SenderName, u.StudentCode, u.Email AS SenderEmail FROM Reflections r JOIN Categories c ON c.Id = r.CategoryId JOIN Users u ON u.Id = r.UserId WHERE ' . STAFF_REFLECTION_SCOPE;
    $params = [$staffId];
    if ($status !== '') { $sql .= ' AND r.Status = ?'; $params[] = $status; }
    $q = $pdo->prepare($sql . ' ORDER BY r.CreatedAt DESC, r.Id DESC');
    $q->execute($params);
    return $q;
}
