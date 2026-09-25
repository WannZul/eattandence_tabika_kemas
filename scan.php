<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth(['admin', 'teacher']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    throw new UserFacingException('Pelancar kiosk hanya menerima POST.', 405);
}
require_csrf();

if (!env_bool('ALLOW_LOCAL_KIOSK_LAUNCH', false)) {
    throw new UserFacingException('Pelancar kiosk tempatan dimatikan. Tetapkan ALLOW_LOCAL_KIOSK_LAUNCH=true untuk ujian satu PC.', 403);
}
if (app_env() === 'production') {
    throw new UserFacingException('Pelancar proses tempatan dilarang dalam production. Jalankan ejen kiosk pada PC kamera.', 403);
}
$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if (!in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    throw new UserFacingException('Butang pelancar hanya boleh ditekan terus dari komputer localhost yang menjalankan PHP.', 403);
}
if (PHP_OS_FAMILY !== 'Windows') {
    throw new UserFacingException('Butang pelancar hanya boleh membuka kamera pada pelayan Windows tempatan.', 409);
}
if ((int) ($_SESSION['last_kiosk_launch_at'] ?? 0) > time() - 10) {
    throw new UserFacingException('Arahan kiosk baru sahaja dihantar. Tunggu tetingkap kamera dibuka.', 429);
}

$batch = realpath(__DIR__ . '/run_face.bat');
$agent = realpath(__DIR__ . '/face_recognition.py');
$python = realpath(__DIR__ . '/.venv/Scripts/python.exe');
if ($batch === false || $agent === false) {
    throw new UserFacingException('Fail ejen kiosk tidak lengkap. Muat turun semula projek.', 500);
}
if ($python === false) {
    throw new UserFacingException('Python .venv belum tersedia. Cipta .venv dan pasang requirements.txt dahulu.', 409);
}
verify_python_kiosk_runtime($python);

$apiKey = env_value('KIOSK_API_KEY');
if ($apiKey === null || strlen($apiKey) < 24) {
    throw new UserFacingException('KIOSK_API_KEY belum dikonfigurasi dengan sekurang-kurangnya 24 aksara.', 409);
}
$apiUrl = env_value('APP_API_URL');
if ($apiUrl === null) {
    $baseUrl = app_base_url();
    if ($baseUrl === '') {
        throw new UserFacingException('Tetapkan APP_BASE_URL atau APP_API_URL sebelum menggunakan pelancar.', 409);
    }
    $apiUrl = $baseUrl . '/api/kiosk.php';
}
if (filter_var($apiUrl, FILTER_VALIDATE_URL) === false) {
    throw new UserFacingException('APP_API_URL tidak sah.', 409);
}
$parts = parse_url($apiUrl);
$scheme = strtolower((string) ($parts['scheme'] ?? ''));
$host = trim(strtolower((string) ($parts['host'] ?? '')), '[]');
$localHttp = app_env() !== 'production' && $scheme === 'http' && in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
if ($scheme !== 'https' && !$localHttp) {
    throw new UserFacingException('API kiosk mesti menggunakan HTTPS, kecuali localhost development.', 409);
}

$kioskId = env_value('LOCAL_KIOSK_ID', gethostname() ?: 'tabika-local') ?? 'tabika-local';
$kioskId = preg_replace('/[^A-Za-z0-9_.-]/', '-', $kioskId) ?: 'tabika-local';
$kioskId = substr($kioskId, 0, 50);
if (strlen($kioskId) < 2) {
    $kioskId = 'tabika-local';
}

// The detached batch process inherits these values from the interactive PHP
// process. No secret is written to a file or exposed to the browser.
putenv('APP_API_URL=' . $apiUrl);
putenv('KIOSK_API_KEY=' . $apiKey);
putenv('KIOSK_ID=' . $kioskId);
putenv('APP_ALLOW_INSECURE_HTTP=' . ($localHttp ? 'true' : 'false'));

$command = 'cmd.exe /D /C start "" /D ' . escapeshellarg(__DIR__) . ' cmd.exe /D /C call ' . escapeshellarg($batch);
$process = @popen($command, 'r');
if ($process === false) {
    throw new UserFacingException('Windows tidak dapat membuka ejen kiosk. Jalankan PHP melalui VS Code PowerShell pada PC ini.', 500);
}
$launchExitCode = pclose($process);
if ($launchExitCode !== 0) {
    throw new UserFacingException('Windows menolak arahan pelancar kiosk. Semak kebenaran terminal PHP.', 500);
}
$_SESSION['last_kiosk_launch_at'] = time();
flash('success', 'Arahan pelancar telah dihantar. Semak tetingkap Python untuk keputusan sync, latihan model dan status kamera sebenar.');
redirect('ambil_kehadiran.php');

function verify_python_kiosk_runtime(string $python): void
{
    $check = 'import sys, cv2, mediapipe, numpy, requests; assert sys.version_info[:2] == (3, 11); assert hasattr(cv2, "face"); print("READY")';
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open([$python, '-c', $check], $descriptors, $pipes, __DIR__, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new UserFacingException('Python .venv tidak dapat diperiksa.', 500);
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + 8.0;
    $exitCode = null;
    do {
        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        $status = proc_get_status($process);
        if (!$status['running']) {
            $exitCode = (int) $status['exitcode'];
            break;
        }
        usleep(50_000);
    } while (microtime(true) < $deadline);

    if ($exitCode === null) {
        proc_terminate($process);
    }
    $stdout .= stream_get_contents($pipes[1]) ?: '';
    $stderr .= stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    if ($exitCode !== 0 || trim($stdout) !== 'READY') {
        error_log('Kiosk Python preflight failed: ' . substr(trim($stderr), 0, 500));
        throw new UserFacingException('Python kiosk belum serasi. Pastikan Python 3.11 dan requirements.txt termasuk opencv-contrib-python telah dipasang dalam .venv.', 409);
    }
}
