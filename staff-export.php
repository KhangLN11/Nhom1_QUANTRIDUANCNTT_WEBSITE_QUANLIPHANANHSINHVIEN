<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('Staff');
require_once __DIR__ . '/includes/staff-query.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { header('Allow: GET'); http_response_code(405); exit('Chỉ hỗ trợ GET.'); }
try {
    $query = staff_reflection_query(db(), (int) $user['Id'], $_GET['status'] ?? '');
    $rows = $query->fetchAll();
} catch (InvalidArgumentException $exception) {
    http_response_code(422); exit('Trạng thái lọc không hợp lệ.');
} catch (PDOException $exception) {
    error_log((string) $exception); http_response_code(503); exit('Chưa xuất được dữ liệu. Vui lòng thử lại.');
}
function csv_safe_cell($value): string
{
    $value = (string) ($value ?? '');
    // Excel must treat user text as data, including formulas after whitespace.
    return preg_match('/^[\s\p{Z}\x{FEFF}]*[=+@-]/u', $value) ? "'" . $value : $value;
}
function csv_write_row($output, array $cells): void
{
    $temp = fopen('php://temp', 'w+');
    fputcsv($temp, array_map('csv_safe_cell', $cells), ',', '"', '');
    rewind($temp); $line = stream_get_contents($temp); fclose($temp);
    fwrite($output, substr($line, 0, -1) . "\r\n");
}
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="CampusVoice-' . date('Ymd-His') . '.csv"');
$output = fopen('php://output', 'wb');
fwrite($output, chr(239) . chr(187) . chr(191));
csv_write_row($output, ['ID', 'Tiêu đề', 'Danh mục', 'Người gửi', 'Mã sinh viên', 'Email', 'Ngày gửi', 'Trạng thái', 'Nội dung']);
$labels = reflection_status_labels();
foreach ($rows as $row) {
    csv_write_row($output, [$row['Id'], $row['Title'], $row['CategoryName'], $row['SenderName'], $row['StudentCode'], $row['SenderEmail'], reflection_date($row['CreatedAt']), $labels[$row['Status']], $row['ReflectionContent']]);
}
fclose($output);
