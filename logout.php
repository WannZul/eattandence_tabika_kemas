<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    throw new UserFacingException('Log keluar mesti dibuat melalui butang Log keluar.', 405);
}
require_auth();
require_csrf();
logout_user();
redirect('login.php');
