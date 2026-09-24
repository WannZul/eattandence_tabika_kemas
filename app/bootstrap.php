<?php
declare(strict_types=1);

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/validation.php';

$https = request_is_https();
if (app_env() === 'production' && !$https) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $origin = configured_https_origin();
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $safeUri = str_starts_with($requestUri, '/') && preg_match('/[\r\n]/', $requestUri) !== 1 ? $requestUri : '/';
    if (in_array($method, ['GET', 'HEAD'], true) && $origin !== null) {
        header('Location: ' . $origin . $safeUri, true, 302);
        exit;
    }
    if ($origin === null) {
        error_log('Invalid production APP_BASE_URL; expected HTTPS without credentials, query, fragment, or unsafe path segments.');
    }
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'HTTPS diperlukan dan konfigurasi URL aplikasi mesti sah.';
    exit;
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionName = env_value('SESSION_NAME', 'eattendance_session') ?? 'eattendance_session';
    if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $sessionName) !== 1) {
        throw new RuntimeException('SESSION_NAME is invalid.');
    }
    session_name($sessionName);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => app_cookie_path(),
        'domain' => '',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
if ($https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self'; style-src 'self'; script-src 'self'");

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

set_exception_handler(static function (Throwable $exception): void {
    $reference = bin2hex(random_bytes(6));
    error_log('Unhandled error ' . $reference . ' ' . $exception::class . ': ' . $exception->getMessage());
    $safe = $exception instanceof InvalidArgumentException || $exception instanceof UserFacingException;
    $message = $safe ? $exception->getMessage() : 'Ralat sistem berlaku. Sila cuba lagi atau hubungi pentadbir.';
    $status = $exception instanceof InvalidArgumentException ? 422 : ($exception instanceof UserFacingException ? $exception->httpStatus() : 500);
    if (app_debug()) {
        $message .= ' [' . $reference . ' ' . $exception->getFile() . ':' . $exception->getLine() . ']';
    }
    if (request_wants_json()) {
        json_response(['success' => false, 'message' => $message, 'reference' => $reference], $status);
    }
    http_response_code($status);
    render_error_page($message);
});
