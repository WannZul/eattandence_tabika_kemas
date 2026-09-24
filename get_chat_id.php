<?php
declare(strict_types=1);
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/validation.php';
http_response_code(410);
json_response(['success'=>false,'message'=>'Endpoint debug getUpdates telah dinyahaktifkan. Gunakan webhook Telegram yang disahkan; mesej dan chat ID tidak dipaparkan.'],410);
