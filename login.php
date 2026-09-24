<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

if (is_logged_in()) {
    redirect(current_user()['must_change_password'] ? 'account.php' : 'dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $username = substr((string) ($_POST['username'] ?? ''), 0, 200);
        $password = substr((string) ($_POST['password'] ?? ''), 0, 4096);
        if (!authenticate_user($username, $password)) {
            throw new InvalidArgumentException('Invalid credentials');
        }
        redirect(current_user()['must_change_password'] ? 'account.php' : 'dashboard.php');
    } catch (InvalidArgumentException $exception) {
        $error = 'Nama pengguna atau kata laluan tidak sah.';
    } catch (UserFacingException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('Login failed: ' . $exception->getMessage());
        $error = 'Log masuk tidak dapat diproses buat masa ini.';
    }
}

render_public_start('Log masuk');
?>
<section class="auth-card">
    <div class="brand-mark large" aria-hidden="true">K</div>
    <h1>Selamat datang</h1>
    <p>Log masuk ke eAttendance TABIKA KEMAS</p>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><span><?= e($error) ?></span></div><?php endif; ?>
    <?php render_flashes(); ?>
    <form method="post" action="login.php" autocomplete="on">
        <?= csrf_field() ?>
        <div class="field"><label for="username">Nama pengguna</label><input class="input" id="username" name="username" maxlength="50" autocomplete="username" required autofocus></div>
        <div class="field"><label for="password">Kata laluan</label><input class="input" type="password" id="password" name="password" maxlength="200" autocomplete="current-password" required></div>
        <button class="btn btn-primary" type="submit">Log masuk</button>
    </form>
    <p class="help-text">Akaun baharu disediakan oleh pentadbir sistem. Tiada kata laluan lalai.</p>
</section>
<?php render_public_end(); ?>
