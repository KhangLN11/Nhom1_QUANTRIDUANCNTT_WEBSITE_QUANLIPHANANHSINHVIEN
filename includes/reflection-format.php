<?php
function reflection_status_labels(): array
{
    return ['Pending' => 'Chờ tiếp nhận', 'InProgress' => 'Đang xử lý',
        'NeedsInfo' => 'Cần bổ sung thông tin', 'Resolved' => 'Đã xử lý', 'Closed' => 'Đã đóng'];
}

function reflection_status(string $status): void
{
    $labels = reflection_status_labels();
    $known = isset($labels[$status]);
    ?><span class="status status-<?= e($known ? $status : 'Pending') ?>"><?= e($labels[$status] ?? 'Chưa xác định') ?></span><?php
}

function reflection_date(string $date): string
{
    return (new DateTimeImmutable($date))->format('d/m/Y H:i');
}

function reflection_id($value): ?int
{
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,9}$/D', $value)) {
        return null;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
    return $id === false ? null : $id;
}
