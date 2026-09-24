<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth(['admin']);
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');http_response_code(405);throw new UserFacingException('Pelancar kiosk hanya menerima POST.', 405);}
require_csrf();
if(!env_bool('ALLOW_LOCAL_KIOSK_LAUNCH',false)){throw new UserFacingException('Pelancar kiosk tempatan dinyahaktifkan. Jalankan ejen pada komputer kiosk.', 403);}
if(PHP_OS_FAMILY!=='Windows'){throw new UserFacingException('Pelancar pilihan ini hanya tersedia pada Windows tempatan.', 409);}
$batch=realpath(__DIR__.'/run_face.bat');if($batch===false){throw new UserFacingException('run_face.bat tidak dijumpai.', 500);}
$process=@popen('cmd /C start "eAttendance Kiosk" '.escapeshellarg($batch),'r');if($process===false){throw new UserFacingException('Ejen kiosk tidak dapat dilancarkan. Semak kebenaran proses PHP.', 500);}pclose($process);flash('success','Arahan pelancar dihantar. Semak tetingkap ejen kiosk pada komputer tempatan.');redirect('ambil_kehadiran.php');
