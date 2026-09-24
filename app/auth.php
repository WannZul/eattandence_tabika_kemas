<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['credential_version'])) {
        return null;
    }
    return [
        'id' => (int) $_SESSION['user_id'],
        'username' => (string) $_SESSION['username'],
        'role' => (string) $_SESSION['role'],
        'credential_version' => (int) $_SESSION['credential_version'],
        'must_change_password' => (bool) ($_SESSION['must_change_password'] ?? false),
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function clear_authentication_session(): void
{
    unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['credential_version'], $_SESSION['must_change_password'], $_SESSION['auth_checked_at'], $_SESSION['last_regeneration']);
}

function auth_failure(string $message, int $status): never
{
    clear_authentication_session();
    if (request_wants_json()) {
        json_response(['success' => false, 'message' => $message, 'reauthenticate' => $status === 401], $status);
    }
    if ($status === 401) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
        flash('error', $message);
        redirect('login.php');
    }
    throw new UserFacingException($message, $status);
}

function require_auth(array $roles = ['admin', 'teacher']): void
{
    $user = current_user();
    if ($user === null) {
        auth_failure('Sesi anda telah tamat. Sila log masuk semula.', 401);
    }

    $stmt = db()->prepare('SELECT username,role,is_active,credential_version,must_change_password FROM users WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $fresh = $stmt->get_result()->fetch_assoc();
    if (!$fresh || (int) $fresh['is_active'] !== 1 || (int) $fresh['credential_version'] !== $user['credential_version']) {
        auth_failure('Sesi anda tidak lagi sah. Sila log masuk semula.', 401);
    }

    $_SESSION['username'] = (string) $fresh['username'];
    $_SESSION['role'] = (string) $fresh['role'];
    $_SESSION['must_change_password'] = (bool) $fresh['must_change_password'];
    $_SESSION['auth_checked_at'] = time();
    $user = current_user();
    if ($user === null || !in_array($user['role'], $roles, true)) {
        if (request_wants_json()) {
            json_response(['success' => false, 'message' => 'Anda tidak mempunyai kebenaran untuk tindakan ini.'], 403);
        }
        throw new UserFacingException('Anda tidak mempunyai kebenaran untuk halaman ini.', 403);
    }

    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($user['must_change_password'] && !in_array($script, ['account.php', 'logout.php'], true)) {
        if (request_wants_json()) {
            json_response(['success' => false, 'message' => 'Kata laluan sementara mesti ditukar sebelum meneruskan.', 'password_change_required' => true], 403);
        }
        flash('warning', 'Tukar kata laluan sementara anda sebelum menggunakan aplikasi.');
        redirect('account.php');
    }

    $lastRotation = (int) ($_SESSION['last_regeneration'] ?? 0);
    if ($lastRotation < time() - 900) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

function login_rate_limit_secret(): string
{
    $secret = env_value('LOGIN_RATE_LIMIT_SECRET');
    if ($secret !== null && strlen($secret) >= 32) {
        return $secret;
    }
    if (app_env() === 'production') {
        throw new RuntimeException('LOGIN_RATE_LIMIT_SECRET is not safely configured.');
    }
    return 'development-only-login-rate-limit-secret';
}

function login_rate_limit_number(string $key, int $default, int $minimum, int $maximum): int
{
    $value = filter_var(env_value($key, (string) $default), FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum, 'max_range' => $maximum]]);
    return $value === false ? $default : (int) $value;
}

function normalized_network_prefix(): string
{
    $address = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $packed = @inet_pton($address);
    if ($packed === false) {
        return 'unknown';
    }
    if (strlen($packed) === 4) {
        return 'ipv4:' . bin2hex(substr($packed, 0, 3)) . '00/24';
    }
    return 'ipv6:' . bin2hex(substr($packed, 0, 8)) . str_repeat('00', 8) . '/64';
}

function login_attempt_keys(string $username): array
{
    $normalized = function_exists('mb_strtolower') ? mb_strtolower(trim($username), 'UTF-8') : strtolower(trim($username));
    $secret = login_rate_limit_secret();
    return [
        'username' => hash_hmac('sha256', 'username:' . $normalized, $secret),
        'network' => hash_hmac('sha256', 'network:' . normalized_network_prefix(), $secret),
    ];
}

function cleanup_login_attempts(): void
{
    static $cleaned = false;
    if ($cleaned) {
        return;
    }
    $cleaned = true;
    db()->query("DELETE FROM login_attempt WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
}

function acquire_login_locks(mysqli $connection, array $keys): array
{
    $names = ['eatt-login-n-' . substr($keys['network'], 0, 40), 'eatt-login-u-' . substr($keys['username'], 0, 40)];
    sort($names, SORT_STRING);
    $acquired = [];
    try {
        foreach ($names as $name) {
            $stmt = $connection->prepare('SELECT GET_LOCK(?,5) acquired');
            $stmt->bind_param('s', $name);
            $stmt->execute();
            if ((int) ($stmt->get_result()->fetch_assoc()['acquired'] ?? 0) !== 1) {
                throw new RuntimeException('Login limiter lock unavailable.');
            }
            $acquired[] = $name;
        }
        return $acquired;
    } catch (Throwable $exception) {
        release_login_locks($connection, $acquired);
        throw $exception;
    }
}

function release_login_locks(mysqli $connection, array $names): void
{
    foreach (array_reverse($names) as $name) {
        try {
            $stmt = $connection->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->bind_param('s', $name);
            $stmt->execute();
        } catch (Throwable $exception) {
            error_log('Login advisory lock release failed: ' . $exception->getMessage());
        }
    }
}

function authenticate_user(string $username, string $password): bool
{
    cleanup_login_attempts();
    $username = trim(substr($username, 0, 200));
    $password = substr($password, 0, 4096);
    $keys = login_attempt_keys($username);
    $connection = db();
    $locks = [];
    try {
        $locks = acquire_login_locks($connection, $keys);
        $connection->begin_transaction();
        try {
            $countsStmt = $connection->prepare("SELECT COALESCE(SUM(username_key=? AND network_key=?),0) pair_attempts,COALESCE(SUM(username_key=?),0) username_attempts,COALESCE(SUM(network_key=?),0) network_attempts FROM login_attempt WHERE attempted_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE) AND (username_key=? OR network_key=?)");
            $countsStmt->bind_param('ssssss', $keys['username'], $keys['network'], $keys['username'], $keys['network'], $keys['username'], $keys['network']);
            $countsStmt->execute();
            $counts = $countsStmt->get_result()->fetch_assoc() ?: [];
            $blocked = (int) ($counts['pair_attempts'] ?? 0) >= login_rate_limit_number('LOGIN_PAIR_MAX_ATTEMPTS', 5, 3, 50)
                || (int) ($counts['username_attempts'] ?? 0) >= login_rate_limit_number('LOGIN_USERNAME_MAX_ATTEMPTS', 20, 10, 200)
                || (int) ($counts['network_attempts'] ?? 0) >= login_rate_limit_number('LOGIN_NETWORK_MAX_ATTEMPTS', 30, 10, 500);

            $stmt = $connection->prepare('SELECT id,username,password_hash,role,credential_version,must_change_password FROM users WHERE username=? AND is_active=1 LIMIT 1');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
            $hash = $user ? (string) $user['password_hash'] : $dummyHash;
            $passwordMatches = password_verify($password, $hash);
            $success = !$blocked && $user !== null && $passwordMatches;

            if (!$success) {
                $failure = $connection->prepare('INSERT INTO login_attempt(username_key,network_key,attempted_at) VALUES(?,?,NOW())');
                $failure->bind_param('ss', $keys['username'], $keys['network']);
                $failure->execute();
                $connection->commit();
                return false;
            }

            $clear = $connection->prepare('DELETE FROM login_attempt WHERE username_key=? AND network_key=?');
            $clear->bind_param('ss', $keys['username'], $keys['network']);
            $clear->execute();
            $update = $connection->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?');
            $userId = (int) $user['id'];
            $update->bind_param('i', $userId);
            $update->execute();
            $connection->commit();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = (string) $user['username'];
            $_SESSION['role'] = in_array($user['role'], ['admin', 'teacher'], true) ? $user['role'] : 'teacher';
            $_SESSION['credential_version'] = (int) $user['credential_version'];
            $_SESSION['must_change_password'] = (bool) $user['must_change_password'];
            $_SESSION['last_regeneration'] = time();
            $_SESSION['auth_checked_at'] = time();
            return true;
        } catch (Throwable $exception) {
            try { $connection->rollback(); } catch (Throwable) { }
            throw $exception;
        }
    } finally {
        release_login_locks($connection, $locks);
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}
