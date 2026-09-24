<?php
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
if ($isCli) {
    require_once __DIR__ . '/app/errors.php';
    require_once __DIR__ . '/app/config.php';
    require_once __DIR__ . '/app/database.php';
    require_once __DIR__ . '/app/validation.php';
} else {
    require_once __DIR__ . '/app/bootstrap.php';
}

$expectedKey = env_value('APP_SETUP_KEY');
if ($expectedKey === null || strlen($expectedKey) < 24) {
    if ($isCli) { fwrite(STDERR, "APP_SETUP_KEY mesti ditetapkan dengan sekurang-kurangnya 24 aksara.\n"); exit(1); }
    http_response_code(503); throw new UserFacingException('Persediaan pentadbir dinyahaktifkan. Tetapkan APP_SETUP_KEY yang kukuh sementara waktu.', 503);
}

function create_initial_admin(string $providedKey, string $username, string $password, string $expectedKey): void
{
    if (!hash_equals($expectedKey, $providedKey)) { throw new UserFacingException('Kunci persediaan tidak sah.', 403); }
    $connection = db();
    $lock = $connection->query("SELECT GET_LOCK('eattendance_initial_admin',10) acquired")->fetch_assoc();
    if ((int) ($lock['acquired'] ?? 0) !== 1) {
        throw new UserFacingException('Persediaan sedang digunakan. Cuba semula sebentar lagi.', 409);
    }
    try {
        $count = (int) ($connection->query('SELECT COUNT(*) AS total FROM users')->fetch_assoc()['total'] ?? 0);
        if ($count > 0) { throw new UserFacingException('Persediaan telah selesai. Buang APP_SETUP_KEY dan urus akaun melalui halaman Pengguna.', 409); }
        $username = require_text($username, 'Nama pengguna', 3, 50);
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $username) !== 1) { throw new InvalidArgumentException('Nama pengguna hanya boleh mengandungi huruf, nombor, titik, sengkang dan garis bawah.'); }
        if (strlen($password) < 12 || strlen($password) > 200) { throw new InvalidArgumentException('Kata laluan mesti antara 12 hingga 200 aksara.'); }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) { throw new RuntimeException('Kata laluan tidak dapat dilindungi.'); }
        $role = 'admin';
        $stmt = $connection->prepare('INSERT INTO users(username,password_hash,role,credential_version,must_change_password) VALUES(?,?,?,1,0)');
        $stmt->bind_param('sss', $username, $hash, $role);
        $stmt->execute();
    } finally {
        try { $connection->query("SELECT RELEASE_LOCK('eattendance_initial_admin')"); } catch (Throwable) { }
    }
}

if ($isCli) {
    $provided = env_value('SETUP_KEY', '') ?? '';
    $username = $argv[1] ?? (env_value('APP_ADMIN_USERNAME', '') ?? '');
    $password = env_value('APP_ADMIN_PASSWORD', '') ?? '';
    try { create_initial_admin($provided, $username, $password, $expectedKey); fwrite(STDOUT, "Akaun pentadbir {$username} berjaya dicipta. Kosongkan APP_SETUP_KEY, SETUP_KEY dan APP_ADMIN_PASSWORD sekarang.\n"); exit(0); }
    catch (Throwable $exception) { fwrite(STDERR, $exception->getMessage() . "\n"); exit(1); }
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        create_initial_admin((string) ($_POST['setup_key'] ?? ''), (string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''), $expectedKey);
        $message = 'Akaun pentadbir pertama berjaya dicipta. Kosongkan APP_SETUP_KEY daripada persekitaran sekarang.';
    } catch (mysqli_sql_exception $exception) {
        $message = $exception->getCode() === 1062 ? 'Nama pengguna sudah wujud.' : 'Akaun tidak dapat dicipta.';
    } catch (InvalidArgumentException|UserFacingException $exception) {
        $message = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('Initial admin setup failed: ' . $exception->getMessage());
        $message = 'Akaun pentadbir tidak dapat dicipta.';
    }
}
render_public_start('Persediaan pentadbir');
?>
<section class="auth-card"><div class="brand-mark large">K</div><h1>Sediakan pentadbir pertama</h1><p>Bootstrap ini hanya mencipta akaun pentadbir pertama. Akaun seterusnya diurus oleh pentadbir melalui halaman Pengguna.</p><?php if ($message): ?><div class="alert <?= str_contains($message, 'berjaya') ? 'alert-success' : 'alert-error' ?>"><span><?= e($message) ?></span></div><?php endif; ?><form method="post"><?= csrf_field() ?><div class="field"><label for="setup_key">APP_SETUP_KEY</label><input class="input" type="password" id="setup_key" name="setup_key" required></div><div class="field"><label for="username">Nama pengguna pentadbir</label><input class="input" id="username" name="username" maxlength="50" autocomplete="username" required></div><div class="field"><label for="password">Kata laluan (minimum 12 aksara)</label><input class="input" type="password" id="password" name="password" minlength="12" maxlength="200" autocomplete="new-password" required></div><div class="field"><label>Peranan</label><input class="input" value="Pentadbir" disabled></div><button class="btn btn-primary" type="submit">Cipta pentadbir pertama</button></form></section>
<?php render_public_end(); ?>
