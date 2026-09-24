<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false && isset($_ENV[$key])) {
        $value = (string) $_ENV[$key];
    }
    if ($value === false || $value === '') {
        return $default;
    }
    return trim((string) $value);
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env_value($key);
    if ($value === null) {
        return $default;
    }
    return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
}

function app_env(): string
{
    return strtolower(env_value('APP_ENV', 'development') ?? 'development');
}

function app_debug(): bool
{
    return app_env() !== 'production' && env_bool('APP_DEBUG', false);
}

function app_timezone(): string
{
    return env_value('APP_TIMEZONE', 'Asia/Kuala_Lumpur') ?? 'Asia/Kuala_Lumpur';
}

function private_storage_path(string $suffix = ''): string
{
    $configured = env_value('PRIVATE_STORAGE_PATH');
    if (app_env() === 'production' && $configured === null) {
        throw new RuntimeException('PRIVATE_STORAGE_PATH is required in production.');
    }
    $base = rtrim($configured ?? APP_ROOT . '/storage/private', '/\\');
    if (app_env() === 'production' && preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $base) !== 1) {
        throw new RuntimeException('PRIVATE_STORAGE_PATH must be absolute in production.');
    }

    // Resolve existing paths, or resolve the existing parent before allowing creation.
    // This prevents paths such as /var/www/app/../app/storage from bypassing the
    // production outside-web-root check through unnormalised ".." segments.
    $resolvedBase = realpath($base);
    if ($resolvedBase === false && app_env() === 'production') {
        $resolvedParent = realpath(dirname($base));
        if ($resolvedParent === false || basename($base) === '' || in_array(basename($base), ['.', '..'], true)) {
            throw new RuntimeException('PRIVATE_STORAGE_PATH parent must already exist and be resolvable in production.');
        }
        $resolvedBase = $resolvedParent . DIRECTORY_SEPARATOR . basename($base);
    }
    if ($resolvedBase === false) {
        $resolvedBase = $base;
    }

    $appRoot = realpath(APP_ROOT) ?: APP_ROOT;
    $normalizedBase = rtrim(str_replace('\\', '/', $resolvedBase), '/');
    $normalizedRoot = rtrim(str_replace('\\', '/', $appRoot), '/');

    $publicRoot = null;
    if (app_env() === 'production') {
        $configuredPublicRoot = env_value('PUBLIC_DOCUMENT_ROOT');
        if ($configuredPublicRoot === null || preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $configuredPublicRoot) !== 1) {
            throw new RuntimeException('PUBLIC_DOCUMENT_ROOT must be an absolute production path.');
        }
        $publicRoot = realpath($configuredPublicRoot);
        if ($publicRoot === false || !is_dir($publicRoot)) {
            throw new RuntimeException('PUBLIC_DOCUMENT_ROOT must resolve to the active web document root.');
        }

        // During web requests, ensure the operator-provided boundary agrees with
        // the server boundary. CLI workers use the explicit setting alone.
        $serverDocumentRoot = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if (PHP_SAPI !== 'cli' && $serverDocumentRoot !== '') {
            $resolvedServerRoot = realpath($serverDocumentRoot);
            if ($resolvedServerRoot === false) {
                throw new RuntimeException('The server DOCUMENT_ROOT cannot be resolved safely.');
            }
            $configuredCompare = rtrim(str_replace('\\', '/', $publicRoot), '/');
            $serverCompare = rtrim(str_replace('\\', '/', $resolvedServerRoot), '/');
            if (PHP_OS_FAMILY === 'Windows') {
                $configuredCompare = strtolower($configuredCompare);
                $serverCompare = strtolower($serverCompare);
            }
            if ($configuredCompare !== $serverCompare) {
                throw new RuntimeException('PUBLIC_DOCUMENT_ROOT does not match the active server DOCUMENT_ROOT.');
            }
        }
    }

    $normalizedPublicRoot = $publicRoot === null ? null : rtrim(str_replace('\\', '/', $publicRoot), '/');
    if (PHP_OS_FAMILY === 'Windows') {
        $normalizedBase = strtolower($normalizedBase);
        $normalizedRoot = strtolower($normalizedRoot);
        $normalizedPublicRoot = $normalizedPublicRoot === null ? null : strtolower($normalizedPublicRoot);
    }
    $insideApp = $normalizedBase === $normalizedRoot || str_starts_with($normalizedBase . '/', $normalizedRoot . '/');
    $insidePublicRoot = $normalizedPublicRoot !== null
        && ($normalizedBase === $normalizedPublicRoot || str_starts_with($normalizedBase . '/', $normalizedPublicRoot . '/'));
    if (app_env() === 'production' && ($insideApp || $insidePublicRoot)) {
        throw new RuntimeException('Production biometric storage must be outside both the application and public document roots.');
    }
    return $suffix === '' ? $resolvedBase : $resolvedBase . DIRECTORY_SEPARATOR . ltrim($suffix, '/\\');
}

function app_base_url(): string
{
    return rtrim(env_value('APP_BASE_URL', '') ?? '', '/');
}

/** @return array<string,mixed>|null */
function validated_app_base_parts(): ?array
{
    $base = app_base_url();
    if ($base === '') {
        return app_env() === 'production' ? null : ['scheme' => 'http', 'host' => 'localhost', 'path' => '/'];
    }
    if (filter_var($base, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $parts = parse_url($base);
    if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        return null;
    }
    $rawPath = (string) ($parts['path'] ?? '/');
    $decodedPath = rawurldecode($rawPath);
    if ($rawPath === '' || $rawPath[0] !== '/' || str_contains($rawPath, '\\') || str_contains($rawPath, '//') || preg_match('/[\x00-\x1F\x7F]/', $decodedPath) === 1 || preg_match('#(?:^|/)\.\.?($|/)#', $decodedPath) === 1 || preg_match('/%(?:2f|5c)/i', $rawPath) === 1) {
        return null;
    }
    $parts['path'] = $rawPath;
    return $parts;
}

function app_cookie_path(): string
{
    $parts = validated_app_base_parts();
    if ($parts === null) {
        throw new RuntimeException('APP_BASE_URL is invalid; a safe session cookie path cannot be derived.');
    }
    $path = (string) ($parts['path'] ?? '/');
    return $path === '/' ? '/' : rtrim($path, '/') . '/';
}

function configured_https_origin(): ?string
{
    $parts = validated_app_base_parts();
    if ($parts === null || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
        return null;
    }
    $host = (string) $parts['host'];
    if (preg_match('/[\r\n]/', $host) === 1) {
        return null;
    }
    $port = isset($parts['port']) ? (int) $parts['port'] : null;
    if ($port !== null && ($port < 1 || $port > 65535)) {
        return null;
    }
    return 'https://' . $host . ($port !== null && $port !== 443 ? ':' . $port : '');
}

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return env_bool('TRUST_PROXY_HEADERS', false) && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

date_default_timezone_set(app_timezone());
if (app_env() === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
