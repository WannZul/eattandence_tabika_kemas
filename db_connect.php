<?php
declare(strict_types=1);
// Compatibility wrapper for older integrations. New pages load app/bootstrap.php.
require_once __DIR__ . '/app/database.php';
$conn = db();
