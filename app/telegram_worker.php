<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/telegram_service.php';

try {
    $limit = isset($argv[1]) ? filter_var($argv[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]) : telegram_outbox_batch_size();
    if ($limit === false) {
        throw new InvalidArgumentException('Had batch mesti nombor antara 1 hingga 20.');
    }
    $result = telegram_process_outbox((int) $limit);
    fwrite(STDOUT, sprintf("Outbox diproses: %d, dihantar: %d, gagal: %d, dibatalkan: %d\n", $result['processed'], $result['sent'], $result['failed'], $result['cancelled']));
    exit($result['failed'] > 0 ? 2 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Telegram worker gagal: ' . $exception->getMessage() . "\n");
    exit(1);
}
