<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function require_valid_date(mixed $value, string $label = 'Tarikh'): string
{
    $date = trim((string) $value);
    if (!valid_date($date)) {
        throw new InvalidArgumentException($label . ' tidak sah.');
    }
    return $date;
}

function valid_month(string $value): bool
{
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1;
}

function require_student_id(mixed $value): string
{
    $id = strtoupper(trim((string) $value));
    if (preg_match('/^[A-Z0-9][A-Z0-9_-]{1,29}$/', $id) !== 1) {
        throw new InvalidArgumentException('ID murid mesti 2 hingga 30 aksara: huruf, nombor, sengkang atau garis bawah.');
    }
    return $id;
}

function require_text(mixed $value, string $label, int $min, int $max): string
{
    $text = trim((string) $value);
    $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    if ($length < $min || $length > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text)) {
        throw new InvalidArgumentException($label . " mesti antara {$min} hingga {$max} aksara.");
    }
    return $text;
}

function require_status(mixed $value): string
{
    $status = (string) $value;
    if (!in_array($status, ['Hadir', 'Tidak Hadir'], true)) {
        throw new InvalidArgumentException('Status kehadiran tidak sah.');
    }
    return $status;
}

function require_positive_int(mixed $value, string $label = 'ID'): int
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($filtered === false) {
        throw new InvalidArgumentException($label . ' tidak sah.');
    }
    return (int) $filtered;
}

function request_wants_json(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    return str_contains($accept, 'application/json') || str_starts_with($contentType, 'application/json');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $location, int $status = 303): never
{
    header('Location: ' . $location, true, $status);
    exit;
}
