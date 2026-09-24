<?php
declare(strict_types=1);

function nav_items(): array
{
    $items = [
        ['dashboard.php', '⌂', 'Dashboard', ['admin', 'teacher']],
        ['registration.php', '＋', 'Daftar Murid', ['admin']],
        ['users.php', '♙', 'Pengguna', ['admin']],
        ['ambil_kehadiran.php', '◎', 'Kiosk Wajah', ['admin', 'teacher']],
        ['manual_kehadiran.php', '✓', 'Kehadiran Manual', ['admin', 'teacher']],
        ['rekod_kehadiran.php', '▤', 'Rekod Kehadiran', ['admin', 'teacher']],
        ['status_kehadiran.php', '◷', 'Status Harian', ['admin', 'teacher']],
        ['laporan_kehadiran.php', '▥', 'Laporan Bulanan', ['admin', 'teacher']],
        ['notice.php', '✉', 'Notis Ketidakhadiran', ['admin', 'teacher']],
        ['account.php', '⚿', 'Akaun Saya', ['admin', 'teacher']],
    ];
    $user = current_user();
    if (($user['must_change_password'] ?? false) === true) {
        return [end($items)];
    }
    $role = $user['role'] ?? '';
    return array_values(array_filter($items, static fn (array $item): bool => in_array($role, $item[3], true)));
}

function render_head(string $title, string $bodyClass = ''): void
{
    echo '<!doctype html><html lang="ms"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<meta name="theme-color" content="#0f6b4f"><title>' . e($title) . ' · eAttendance TABIKA KEMAS</title>';
    echo '<link rel="stylesheet" href="assets/css/app.css"></head><body class="' . e($bodyClass) . '">';
}

function render_flashes(): void
{
    foreach (consume_flashes() as $message) {
        $type = in_array($message['type'] ?? '', ['success', 'error', 'warning', 'info'], true) ? $message['type'] : 'info';
        echo '<div class="alert alert-' . e($type) . '" role="status"><span>' . e($message['message'] ?? '') . '</span><button type="button" class="alert-close" aria-label="Tutup">×</button></div>';
    }
}

function render_header(string $title, string $subtitle = ''): void
{
    render_head($title, 'app-body');
    $user = current_user();
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $home = ($user['must_change_password'] ?? false) ? 'account.php' : 'dashboard.php';
    echo '<a class="skip-link" href="#main-content">Langkau ke kandungan</a><header class="topbar"><a class="brand" href="' . $home . '"><span class="brand-mark" aria-hidden="true">K</span><span><strong>eAttendance</strong><small>TABIKA KEMAS</small></span></a>';
    echo '<button class="nav-toggle" type="button" aria-controls="sidebar" aria-expanded="false"><span aria-hidden="true">☰</span><span class="nav-toggle-label sr-only">Buka menu</span></button></header>';
    echo '<div class="app-shell"><aside class="sidebar" id="sidebar" aria-label="Navigasi utama" aria-hidden="false"><div class="user-panel"><span class="avatar">' . e(strtoupper(substr($user['username'] ?? 'P', 0, 1))) . '</span><div><strong>' . e($user['username'] ?? '') . '</strong><small>' . e(($user['role'] ?? '') === 'admin' ? 'Pentadbir' : 'Guru') . '</small></div></div><nav><ul>';
    foreach (nav_items() as [$href, $icon, $label]) {
        $active = $current === $href ? ' class="active" aria-current="page"' : '';
        echo '<li><a href="' . e($href) . '"' . $active . '><span aria-hidden="true">' . e($icon) . '</span>' . e($label) . '</a></li>';
    }
    echo '</ul></nav><form class="logout-form" method="post" action="logout.php">' . csrf_field() . '<button class="nav-logout" type="submit"><span aria-hidden="true">↪</span> Log keluar</button></form></aside><div class="sidebar-backdrop" data-sidebar-close></div>';
    echo '<main class="main-content" id="main-content"><div class="page-heading"><div><p class="eyebrow">eAttendance TABIKA KEMAS</p><h1>' . e($title) . '</h1>' . ($subtitle !== '' ? '<p>' . e($subtitle) . '</p>' : '') . '</div></div>';
    render_flashes();
}

function render_footer(): void
{
    echo '<footer class="site-footer">Data kehadiran dilindungi · ' . date('Y') . ' eAttendance TABIKA KEMAS</footer></main></div><script src="assets/js/app.js" defer></script></body></html>';
}

function render_public_start(string $title): void { render_head($title, 'auth-body'); echo '<main class="auth-main" id="main-content">'; }
function render_public_end(): void { echo '</main><script src="assets/js/app.js" defer></script></body></html>'; }
function status_badge(?string $status): string { return $status === 'Hadir' ? '<span class="badge badge-success">Hadir</span>' : ($status === 'Tidak Hadir' ? '<span class="badge badge-danger">Tidak Hadir</span>' : '<span class="badge badge-muted">Belum Ditanda</span>'); }
function face_status_badge(?string $status): string { return $status === 'ready' ? '<span class="badge badge-success">Sedia</span>' : ($status === 'invalid' ? '<span class="badge badge-danger">Tidak sah</span>' : '<span class="badge badge-warning">Menunggu validasi</span>'); }
function render_empty(string $title, string $description): void { echo '<div class="empty-state"><span class="empty-icon" aria-hidden="true">○</span><h2>' . e($title) . '</h2><p>' . e($description) . '</p></div>'; }
function render_error_page(string $message): never
{
    if (!headers_sent()) { header('Content-Type: text/html; charset=UTF-8'); }
    render_public_start('Ralat');
    echo '<section class="auth-card"><div class="brand-mark large">!</div><h1>Permintaan tidak dapat diproses</h1><div class="alert alert-error"><span>' . e($message) . '</span></div><a class="btn btn-primary" href="' . (is_logged_in() ? (current_user()['must_change_password'] ? 'account.php' : 'dashboard.php') : 'login.php') . '">Kembali</a></section>';
    render_public_end();
    exit;
}
