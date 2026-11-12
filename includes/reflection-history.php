<?php
require_once __DIR__ . '/reflection-format.php';

function reflection_version($value): ?int
{
    if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,9})$/D', $value)) { return null; }
    $version = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 4294967295]]);
    return $version === false ? null : $version;
}

function validate_reflection_text(string $content): string
{
    $content = trim($content);
    if (!mb_check_encoding($content, 'UTF-8') || !preg_match('/[^\s\p{Z}]/u', $content) || mb_strlen($content, 'UTF-8') > 10000) {
        throw new InvalidArgumentException('Nội dung phải có từ 1 đến 10.000 ký tự, không chỉ gồm khoảng trắng.');
    }
    return $content;
}

function reflection_activity(PDO $pdo, int $id, int $actor, string $type, string $content, int $version, ?int $responseId = null): void
{
    $q = $pdo->prepare('INSERT INTO ReflectionActivities (ReflectionId, UserId, Type, Content, Version, ResponseId) VALUES (?, ?, ?, ?, ?, ?)');
    $q->execute([$id, $actor, $type, $content, $version, $responseId]);
}

// Caller must first authorize access to this reflection, as both detail controllers do.
function reflection_history(PDO $pdo, int $id, bool $student): array
{
    $sql = "SELECT a.Content, a.CreatedAt, a.Type, u.FullName, 1 AS SourceOrder, a.Id AS SortId FROM ReflectionActivities a JOIN Users u ON u.Id = a.UserId WHERE a.ReflectionId = ? UNION ALL SELECT s.Content, s.CreatedAt, 'Response' AS Type, u.FullName, 0 AS SourceOrder, s.Id AS SortId FROM Responses s JOIN Users u ON u.Id = s.UserId WHERE s.ReflectionId = ? AND NOT EXISTS (SELECT 1 FROM ReflectionActivities a WHERE a.ResponseId = s.Id)";
    if ($student) { $sql .= " AND u.Role = 'Staff'"; }
    $q = $pdo->prepare($sql . ' ORDER BY CreatedAt ASC, SourceOrder ASC, SortId ASC');
    $q->execute([$id, $id]);
    return $q->fetchAll();
}

function render_reflection_history(array $history): void
{
    $labels = ['RequestInfo' => 'Yêu cầu bổ sung thông tin', 'Supplement' => 'Sinh viên bổ sung nội dung', 'Resolved' => 'Kết quả xử lý', 'Closed' => 'Lý do kết thúc hồ sơ', 'Response' => 'Câu trả lời'];
    ?><section class="info-card responses-section" aria-labelledby="history-title"><span class="section-tag">LỊCH SỬ TRAO ĐỔI</span><h2 id="history-title">Yêu cầu, bổ sung & phản hồi</h2><?php
    if (!$history) { ?><p class="muted">Chưa có câu trả lời hoặc lượt bổ sung. Các trao đổi sẽ hiển thị tại đây theo thứ tự thời gian.</p><?php }
    foreach ($history as $entry) {
        ?><article class="response-card activity-<?= e($entry['Type']) ?>"><h3><?= e($labels[$entry['Type']] ?? 'Trao đổi') ?></h3><header><strong><?= e($entry['FullName']) ?></strong><time class="muted" datetime="<?= e(str_replace(' ', 'T', $entry['CreatedAt'])) ?>"><?= e(reflection_date($entry['CreatedAt'])) ?></time></header><div class="plain-text"><?= e($entry['Content']) ?></div></article><?php
    }
    ?></section><?php
}
