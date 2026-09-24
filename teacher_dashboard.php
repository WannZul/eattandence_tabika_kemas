<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth(['teacher']);
flash('info', 'Paparan guru kini menggunakan Dashboard bersama dengan akses mengikut peranan.');
redirect('dashboard.php');
