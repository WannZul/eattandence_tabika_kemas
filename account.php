<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth();

function require_new_account_password(mixed $value): string
{
    $password = (string) $value;
    if (strlen($password) < 12 || strlen($password) > 200) {
        throw new InvalidArgumentException('Kata laluan baharu mesti antara 12 hingga 200 aksara.');
    }
    return $password;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $currentPassword = substr((string) ($_POST['current_password'] ?? ''), 0, 4096);
        $newPassword = require_new_account_password($_POST['new_password'] ?? '');
        $confirmation = substr((string) ($_POST['new_password_confirmation'] ?? ''), 0, 201);
        if (!hash_equals($newPassword, $confirmation)) {
            throw new InvalidArgumentException('Pengesahan kata laluan baharu tidak sepadan.');
        }
        if (hash_equals($currentPassword, $newPassword)) {
            throw new InvalidArgumentException('Kata laluan baharu mesti berbeza daripada kata laluan semasa.');
        }
        $connection = db();
        $connection->begin_transaction();
        try {
            $userId = (int) current_user()['id'];
            $stmt = $connection->prepare('SELECT password_hash,credential_version FROM users WHERE id=? AND is_active=1 FOR UPDATE');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row || !password_verify($currentPassword, (string) $row['password_hash'])) {
                throw new InvalidArgumentException('Kata laluan semasa tidak sah.');
            }
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            if ($hash === false) {
                throw new RuntimeException('Password hashing failed.');
            }
            $update = $connection->prepare('UPDATE users SET password_hash=?,credential_version=credential_version+1,must_change_password=0 WHERE id=?');
            $update->bind_param('si', $hash, $userId);
            $update->execute();
            $versionStmt = $connection->prepare('SELECT credential_version FROM users WHERE id=?');
            $versionStmt->bind_param('i', $userId);
            $versionStmt->execute();
            $newVersion = (int) $versionStmt->get_result()->fetch_assoc()['credential_version'];
            $connection->commit();
            session_regenerate_id(true);
            $_SESSION['credential_version'] = $newVersion;
            $_SESSION['must_change_password'] = false;
            $_SESSION['last_regeneration'] = time();
            flash('success', 'Kata laluan berjaya ditukar. Semua sesi lain telah dibatalkan.');
            redirect('account.php');
        } catch (Throwable $exception) {
            try { $connection->rollback(); } catch (Throwable) { }
            throw $exception;
        }
    } catch (InvalidArgumentException $exception) {
        flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('Account password change failed: ' . $exception->getMessage());
        flash('error', 'Kata laluan tidak dapat ditukar buat masa ini.');
    }
    redirect('account.php');
}

$forced = current_user()['must_change_password'];
render_header('Akaun Saya', $forced ? 'Kata laluan sementara mesti ditukar sebelum meneruskan' : 'Tukar kata laluan dan batalkan sesi lain');
?>
<div class="content-stack">
<?php if ($forced): ?><section class="alert alert-warning"><span>Kata laluan ini diberikan oleh pentadbir dan bersifat sementara. Cipta kata laluan peribadi sebelum menggunakan aplikasi.</span></section><?php endif; ?>
<section class="card"><h2>Tukar kata laluan</h2><form class="grid" method="post" action="account.php"><?= csrf_field() ?><div class="field"><label for="current-password">Kata laluan semasa</label><input class="input" id="current-password" type="password" name="current_password" maxlength="200" autocomplete="current-password" required></div><div class="grid grid-2"><div class="field"><label for="new-password">Kata laluan baharu</label><input class="input" id="new-password" type="password" name="new_password" minlength="12" maxlength="200" autocomplete="new-password" required><small>Gunakan sekurang-kurangnya 12 aksara dan jangan guna semula kata laluan semasa.</small></div><div class="field"><label for="new-password-confirmation">Sahkan kata laluan baharu</label><input class="input" id="new-password-confirmation" type="password" name="new_password_confirmation" minlength="12" maxlength="200" autocomplete="new-password" required></div></div><div><button class="btn btn-primary" type="submit">Tukar kata laluan</button></div></form></section>
</div>
<?php render_footer(); ?>
