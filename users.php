<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth(['admin']);

function require_account_username(mixed $value): string
{
    $username = require_text($value, 'Nama pengguna', 3, 50);
    if (preg_match('/^[A-Za-z0-9_.-]+$/', $username) !== 1) {
        throw new InvalidArgumentException('Nama pengguna hanya boleh mengandungi huruf, nombor, titik, sengkang dan garis bawah.');
    }
    return $username;
}

function require_account_password(mixed $value): string
{
    $password = (string) $value;
    if (strlen($password) < 12 || strlen($password) > 200) {
        throw new InvalidArgumentException('Kata laluan mesti antara 12 hingga 200 aksara.');
    }
    return $password;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create') {
            $username = require_account_username($_POST['username'] ?? '');
            $password = require_account_password($_POST['password'] ?? '');
            $role = (string) ($_POST['role'] ?? '');
            if (!in_array($role, ['admin', 'teacher'], true)) {
                throw new InvalidArgumentException('Peranan akaun tidak sah.');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($hash === false) {
                throw new RuntimeException('Password hashing failed.');
            }
            $temporary = 1;
            $stmt = db()->prepare('INSERT INTO users(username,password_hash,role,is_active,must_change_password) VALUES(?,?,?,1,?)');
            $stmt->bind_param('sssi', $username, $hash, $role, $temporary);
            $stmt->execute();
            flash('success', 'Akaun baharu dicipta. Pengguna mesti menukar kata laluan sementara pada log masuk pertama.');
        } elseif ($action === 'status') {
            $targetId = require_positive_int($_POST['user_id'] ?? null, 'Akaun');
            $activate = filter_var($_POST['activate'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);
            if ($activate === false) {
                throw new InvalidArgumentException('Status akaun tidak sah.');
            }
            $connection = db();
            $connection->begin_transaction();
            try {
                $stmt = $connection->prepare('SELECT id,username,role,is_active FROM users WHERE id=? FOR UPDATE');
                $stmt->bind_param('i', $targetId);
                $stmt->execute();
                $target = $stmt->get_result()->fetch_assoc();
                if (!$target) {
                    throw new InvalidArgumentException('Akaun tidak dijumpai.');
                }
                if ((int) $activate === 0 && $targetId === (int) current_user()['id']) {
                    throw new UserFacingException('Anda tidak boleh menyahaktifkan akaun sendiri.', 409);
                }
                if ((int) $activate === 0 && $target['role'] === 'admin' && (int) $target['is_active'] === 1) {
                    $admins = $connection->query("SELECT id FROM users WHERE role='admin' AND is_active=1 FOR UPDATE")->num_rows;
                    if ($admins <= 1) {
                        throw new UserFacingException('Pentadbir aktif terakhir tidak boleh dinyahaktifkan.', 409);
                    }
                }
                $update = $connection->prepare('UPDATE users SET is_active=? WHERE id=?');
                $update->bind_param('ii', $activate, $targetId);
                $update->execute();
                $connection->commit();
                flash('success', (int) $activate === 1 ? 'Akaun berjaya diaktifkan.' : 'Akaun berjaya dinyahaktifkan.');
            } catch (Throwable $exception) {
                try { $connection->rollback(); } catch (Throwable) { }
                throw $exception;
            }
        } elseif ($action === 'reset_password') {
            $targetId = require_positive_int($_POST['user_id'] ?? null, 'Akaun');
            $password = require_account_password($_POST['password'] ?? '');
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($hash === false) {
                throw new RuntimeException('Password hashing failed.');
            }
            $stmt = db()->prepare('UPDATE users SET password_hash=?,credential_version=credential_version+1,must_change_password=1 WHERE id=?');
            $stmt->bind_param('si', $hash, $targetId);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) {
                throw new InvalidArgumentException('Akaun tidak dijumpai.');
            }
            flash('success', 'Kata laluan sementara ditetapkan. Semua sesi lama dibatalkan dan pengguna mesti menukarnya.');
        } else {
            throw new InvalidArgumentException('Tindakan akaun tidak sah.');
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('User management database error: ' . $exception->getMessage());
        flash('error', $exception->getCode() === 1062 ? 'Nama pengguna sudah digunakan.' : 'Akaun tidak dapat dikemas kini.');
    } catch (InvalidArgumentException|UserFacingException $exception) {
        flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('User management failed: ' . $exception->getMessage());
        flash('error', 'Akaun tidak dapat dikemas kini.');
    }
    redirect('users.php');
}

$users = db()->query('SELECT id,username,role,is_active,must_change_password,last_login_at,created_at FROM users ORDER BY is_active DESC,role=\'admin\' DESC,username')->fetch_all(MYSQLI_ASSOC);
render_header('Pengguna', 'Cipta dan urus akaun pentadbir atau guru');
?>
<div class="content-stack">
<section class="alert alert-info"><span>Setiap kakitangan perlu akaun sendiri. Akaun baharu dan kata laluan yang ditetapkan semula bersifat sementara serta mesti ditukar oleh pemilik.</span></section>
<section class="card"><h2>Cipta akaun</h2><form class="grid grid-3" method="post" action="users.php"><?= csrf_field() ?><input type="hidden" name="action" value="create"><div class="field"><label for="username">Nama pengguna</label><input class="input" id="username" name="username" maxlength="50" autocomplete="off" required></div><div class="field"><label for="role">Peranan</label><select id="role" name="role" required><option value="teacher">Guru</option><option value="admin">Pentadbir</option></select></div><div class="field"><label for="password">Kata laluan sementara</label><input class="input" type="password" id="password" name="password" minlength="12" maxlength="200" autocomplete="new-password" required></div><div><button class="btn btn-primary" type="submit">Cipta akaun</button></div></form></section>
<section class="card"><h2>Senarai pengguna</h2><p class="muted">Versi kelayakan disemak pada setiap permintaan terlindung; reset kata laluan membatalkan sesi lama serta-merta.</p><div class="table-wrap"><table class="data-table responsive"><thead><tr><th>Pengguna</th><th>Peranan</th><th>Status</th><th>Log masuk terakhir</th><th>Tindakan</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td data-label="Pengguna"><strong><?= e($user['username']) ?></strong><?= (int) $user['id'] === (int) current_user()['id'] ? '<br><small class="muted">Akaun anda</small>' : '' ?></td><td data-label="Peranan"><?= $user['role'] === 'admin' ? 'Pentadbir' : 'Guru' ?></td><td data-label="Status"><?= (int) $user['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-danger">Tidak aktif</span>' ?><?= (int) $user['must_change_password'] === 1 ? '<br><small class="muted">Perlu tukar kata laluan</small>' : '' ?></td><td data-label="Log masuk terakhir"><?= e($user['last_login_at'] ? date('d/m/Y h:i A', strtotime($user['last_login_at'])) : 'Belum pernah') ?></td><td data-label="Tindakan"><div class="button-row"><form method="post" action="users.php"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>"><input type="hidden" name="activate" value="<?= (int) $user['is_active'] === 1 ? 0 : 1 ?>"><button class="btn <?= (int) $user['is_active'] === 1 ? 'btn-danger' : 'btn-secondary' ?>" type="submit" <?= (int) $user['id'] === (int) current_user()['id'] ? 'disabled' : '' ?>><?= (int) $user['is_active'] === 1 ? 'Nyahaktif' : 'Aktifkan' ?></button></form><details><summary class="btn btn-secondary">Tetap semula kata laluan</summary><form class="inline-reset" method="post" action="users.php"><?= csrf_field() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>"><label class="sr-only" for="reset-<?= (int) $user['id'] ?>">Kata laluan baharu untuk <?= e($user['username']) ?></label><input class="input" id="reset-<?= (int) $user['id'] ?>" type="password" name="password" minlength="12" maxlength="200" autocomplete="new-password" placeholder="Minimum 12 aksara" required><button class="btn btn-warning" type="submit">Simpan</button></form></details></div></td></tr><?php endforeach; ?></tbody></table></div></section>
</div>
<?php render_footer(); ?>
