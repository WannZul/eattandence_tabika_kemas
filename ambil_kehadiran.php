<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth();

$launcherEnabled = env_bool('ALLOW_LOCAL_KIOSK_LAUNCH', false);
$isWindows = PHP_OS_FAMILY === 'Windows';
$isDevelopment = app_env() !== 'production';
$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$isLoopback = in_array($remoteAddress, ['127.0.0.1', '::1'], true);
$virtualPython = __DIR__ . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
$pythonReady = is_file($virtualPython);
$agentReady = is_file(__DIR__ . '/face_recognition.py') && is_file(__DIR__ . '/run_face.bat');
$kioskKey = env_value('KIOSK_API_KEY');
$keyReady = $kioskKey !== null && strlen($kioskKey) >= 24;
$configuredApiUrl = env_value('APP_API_URL');
$derivedApiUrl = $configuredApiUrl ?? (app_base_url() !== '' ? app_base_url() . '/api/kiosk.php' : null);
$urlReady = $derivedApiUrl !== null && filter_var($derivedApiUrl, FILTER_VALIDATE_URL) !== false;
$launchReady = $launcherEnabled && $isWindows && $isDevelopment && $isLoopback && $pythonReady && $agentReady && $keyReady && $urlReady;

$faceStats = db()->query("SELECT COUNT(*) total,COALESCE(SUM(face_status='ready'),0) ready_count,COALESCE(SUM(face_status='pending'),0) pending_count,COALESCE(SUM(face_status='invalid'),0) invalid_count FROM student")->fetch_assoc() ?: [];
render_header('Kiosk Kehadiran Wajah', 'Mulakan kamera dan analisis wajah pada komputer Windows yang sama');
?>
<div class="content-stack">
    <section class="alert alert-info"><span><strong>Mod satu komputer:</strong> laman web, MySQL, Python dan webcam boleh berjalan pada laptop yang sama. Butang di bawah membuka tetingkap Python tempatan; hasil kehadiran terus muncul di Dashboard.</span></section>

    <section class="grid grid-4" aria-label="Status profil wajah">
        <article class="card stat-card"><span class="stat-icon" aria-hidden="true">♙</span><div><strong><?= (int) ($faceStats['total'] ?? 0) ?></strong><span>Jumlah murid</span></div></article>
        <article class="card stat-card"><span class="stat-icon" aria-hidden="true">✓</span><div><strong><?= (int) ($faceStats['ready_count'] ?? 0) ?></strong><span>Profil sedia</span></div></article>
        <article class="card stat-card"><span class="stat-icon" aria-hidden="true">◷</span><div><strong><?= (int) ($faceStats['pending_count'] ?? 0) ?></strong><span>Menunggu validasi</span></div></article>
        <article class="card stat-card"><span class="stat-icon" aria-hidden="true">!</span><div><strong><?= (int) ($faceStats['invalid_count'] ?? 0) ?></strong><span>Perlu imej semula</span></div></article>
    </section>

    <section class="card">
        <h2>Mulakan Face ID Attendance</h2>
        <p>Klik sekali untuk membuka kamera Python pada PC ini. Ejen akan menyegerakkan imej terkini, memvalidasi profil, melatih model LBPH dan mula menganalisis wajah berdaftar.</p>
        <?php if ($launchReady): ?>
            <form method="post" action="scan.php">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit" data-confirm="Buka kamera Face ID pada komputer ini sekarang?">◎ Mulakan Imbasan Wajah</button>
            </form>
            <p class="help-text">Tetingkap kamera dibuka di desktop Windows. Tekan Q untuk berhenti atau R untuk sync semula dataset.</p>
        <?php else: ?>
            <button class="btn btn-primary" type="button" disabled>◎ Mulakan Imbasan Wajah</button>
            <div class="alert alert-warning" role="status"><span>Pelancar belum sedia pada komputer ini. Lengkapkan item bertanda belum sedia di bawah, kemudian mulakan semula pelayan PHP.</span></div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Semakan pelancar tempatan</h2>
        <div class="table-wrap"><table class="data-table responsive"><thead><tr><th>Keperluan</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
            <tr><td data-label="Keperluan">Windows development pada PC yang sama</td><td data-label="Status"><?= $isWindows && $isDevelopment && $isLoopback ? '<span class="badge badge-success">Sedia</span>' : '<span class="badge badge-danger">Tidak tersedia</span>' ?></td><td data-label="Tindakan">Mesti development dan dibuka terus melalui <code>127.0.0.1</code>, <code>localhost</code> atau <code>::1</code>.</td></tr>
            <tr><td data-label="Keperluan">Fail virtual environment</td><td data-label="Status"><?= $pythonReady ? '<span class="badge badge-success">Ditemui</span>' : '<span class="badge badge-warning">Belum sedia</span>' ?></td><td data-label="Tindakan"><code>py -3.11 -m venv .venv</code> kemudian pasang <code>requirements.txt</code>. Versi 3.11 dan <code>cv2.face</code> disahkan apabila butang ditekan.</td></tr>
            <tr><td data-label="Keperluan">Fail ejen kiosk</td><td data-label="Status"><?= $agentReady ? '<span class="badge badge-success">Sedia</span>' : '<span class="badge badge-danger">Tidak lengkap</span>' ?></td><td data-label="Tindakan">Pastikan <code>face_recognition.py</code> dan <code>run_face.bat</code> wujud.</td></tr>
            <tr><td data-label="Keperluan">Kunci API kiosk</td><td data-label="Status"><?= $keyReady ? '<span class="badge badge-success">Sedia</span>' : '<span class="badge badge-warning">Belum dikonfigurasi</span>' ?></td><td data-label="Tindakan">Tetapkan <code>KIOSK_API_KEY</code> sebelum memulakan PHP.</td></tr>
            <tr><td data-label="Keperluan">URL API kiosk</td><td data-label="Status"><?= $urlReady ? '<span class="badge badge-success">Sedia</span>' : '<span class="badge badge-warning">Belum dikonfigurasi</span>' ?></td><td data-label="Tindakan">Tetapkan <code>APP_BASE_URL</code> atau <code>APP_API_URL</code>.</td></tr>
            <tr><td data-label="Keperluan">Pelancar dibenarkan</td><td data-label="Status"><?= $launcherEnabled ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Dimatikan</span>' ?></td><td data-label="Tindakan">Untuk localhost sahaja, tetapkan <code>ALLOW_LOCAL_KIOSK_LAUNCH=true</code>.</td></tr>
        </tbody></table></div>
    </section>

    <section class="grid grid-3">
        <article class="card"><span class="stat-icon" aria-hidden="true">1</span><h2>Klik butang</h2><p>Tetingkap arahan dan kamera Python akan dibuka pada komputer yang menjalankan pelayan PHP.</p></article>
        <article class="card"><span class="stat-icon" aria-hidden="true">2</span><h2>Pandang kamera</h2><p>Kekal di hadapan webcam selama beberapa saat sehingga nama dan kotak hijau dipaparkan.</p></article>
        <article class="card"><span class="stat-icon" aria-hidden="true">3</span><h2>Semak rekod</h2><p>Kehadiran yang diterima akan muncul sebagai <strong>Hadir</strong> dengan sumber <strong>Kiosk wajah</strong>.</p></article>
    </section>

    <section class="card"><h2>Keselamatan hosting</h2><p class="muted"><code>ALLOW_LOCAL_KIOSK_LAUNCH</code> kekal <strong>false</strong> pada hosting awam. Domain awam tidak boleh membuka kamera laptop pengguna; jalankan ejen secara manual atau melalui pemasangan kiosk yang diluluskan pada PC tersebut.</p><a class="btn btn-secondary" href="rekod_kehadiran.php">Lihat rekod kehadiran</a></section>
</div>
<?php render_footer(); ?>
