# eAttendance TABIKA KEMAS

Aplikasi kehadiran untuk PHP 8.1+, MySQL 8/MariaDB 10.4 dan ejen kiosk Python 3.11. Kamera serta model wajah berjalan pada komputer kiosk; hosting awam menyediakan UI, DB, storan peribadi, API HTTPS dan outbox Telegram.

## Keperluan dan pemasangan baharu

- PHP 8.1+ dengan `mysqli/mysqlnd`, JSON, fungsi imej dan cURL; MySQL 8 atau MariaDB 10.4+ dengan InnoDB; HTTPS production.
- Kiosk memerlukan Python 3.11, webcam, desktop GUI dan `pip install -r requirements.txt`.
- Import `attendance.sql` **hanya untuk pangkalan kosong**. Ia menggugurkan jadual bernama sama. Pemasangan sedia ada mesti mengikut `MIGRATION.md`.
- Projek tidak membaca `.env`; salin nama dalam `.env.example` ke environment Apache/PHP-FPM/panel/cron sebenar.
- Production memerlukan pengguna DB bukan root, kata laluan DB, `APP_ENV=production`, HTTPS `APP_BASE_URL`, `PUBLIC_DOCUMENT_ROOT` yang sepadan dengan document root pelayan, secret bebas untuk login/kiosk/webhook, dan `PRIVATE_STORAGE_PATH` mutlak di luar kedua-dua public document root dan direktori aplikasi.

Cipta pentadbir pertama menggunakan `/setup_admin.php` atau CLI. Kata laluan yang dipilih pentadbir pertama bukan kata laluan sementara:

```bash
APP_SETUP_KEY='configured-server-key' SETUP_KEY='configured-server-key' \
APP_ADMIN_PASSWORD='a-strong-unique-password' php setup_admin.php admin
```

Buang semua pemboleh ubah setup selepas berjaya. Akaun yang dicipta melalui **Pengguna** menerima kata laluan sementara dan dipaksa ke **Akaun Saya** pada log masuk pertama.

## URL, cookie dan ralat

`APP_BASE_URL` boleh menjadi `https://attendance.example.my` atau `https://portal.example.my/eattendance`. Path cookie sesi diperoleh hanya daripada URL yang disahkan (`/` atau `/eattendance/`), bukan `Host` permintaan. URL dengan credentials, query, fragment, encoded slash, backslash atau segmen `.`/`..` ditolak. Gunakan `SESSION_NAME` unik jika beberapa pemasangan berkongsi origin. `TRUST_PROXY_HEADERS=true` hanya di belakang proxy dipercayai yang menulis semula `X-Forwarded-Proto`.

`PUBLIC_DOCUMENT_ROOT` mesti menunjuk kepada direktori kanonik yang benar-benar disajikan oleh Apache/Nginx, contohnya `/home/account/public_html`. Untuk aplikasi di `public_html/eattendance`, nilainya masih `/home/account/public_html`. Permintaan web menyemaknya dengan `DOCUMENT_ROOT`, manakala worker CLI menggunakan nilai eksplisit yang sama. `PRIVATE_STORAGE_PATH` ditolak jika berada di bawah public root atau direktori aplikasi, termasuk laluan yang cuba menggunakan segmen `..`; parent storan mesti wujud dan boleh disahkan sebelum production bermula.

Hanya ralat validasi dan `UserFacingException` yang disengajakan dipaparkan. Ralat DB/runtime lain dilog dengan rujukan dan mendapat mesej production tetap; diagnostik SQL tidak dihantar kepada pelayar/API.

## Akaun, sesi dan had login

- Sesi menyimpan `users.credential_version` dan menyemaknya pada setiap permintaan terlindung. Reset atau pertukaran kata laluan menaikkan versi dan membatalkan semua sesi lama. Pertukaran sendiri mengemas kini hanya sesi semasa.
- Reset pentadbir menetapkan `must_change_password=1`. Pengguna itu hanya boleh membuka `account.php` atau log keluar sehingga mengesahkan kata laluan semasa dan memilih kata laluan baharu.
- Had login menggunakan advisory lock MySQL bagi HMAC nama pengguna dan HMAC prefix rangkaian dalam urutan deterministik. Dalam satu claim atomik, aplikasi mengira pasangan nama+rangkaian (lalai 5), nama global lebih tinggi (20), dan rangkaian (30) dalam 15 minit. Pengguna tidak wujud tetap menjalankan tepat satu `password_verify` dengan hash dummy. Semua kegagalan adalah generik.
- Konfigurasi: `LOGIN_PAIR_MAX_ATTEMPTS`, `LOGIN_USERNAME_MAX_ATTEMPTS`, `LOGIN_NETWORK_MAX_ATTEMPTS`, dan `LOGIN_RATE_LIMIT_SECRET` minimum 32 aksara. Prefix ialah `/24` IPv4 atau `/64` IPv6 daripada `REMOTE_ADDR`.

## Dataset wajah immutable dan kiosk

Set aktif dipilih oleh `student.face_dataset_version`; setiap `face_image` menyimpan `dataset_version`. Enam fail berada di:

```text
PRIVATE_STORAGE_PATH/faces/<student_id>/<random-version>/01.jpg ... 06.jpg
```

Create/replace menyiapkan direktori versi baharu tanpa menamakan semula atau memadam versi aktif. Transaksi kemudian memasukkan metadata dan menukar pointer. Jika proses mati sebelum commit, pointer/fail lama kekal autoritatif dan versi baharu hanyalah orphan; selepas commit, versi baharu autoritatif. Pengecualian normal cuba membuang versi baharu yang belum commit. Selepas replacement commit, cleanup versi lama adalah best-effort dan kegagalan hanya dilog—aplikasi tidak mendakwa pemulihan yang tidak berlaku. Metadata `face_image` lama dikekalkan sebagai sejarah; endpoint dataset/image hanya mengembalikan baris versi semasa.

Sebelum replacement, server menyemak enam baris semasa, nama fail, SHA-256, JPEG dan dimensi. Jangan sunting fail wajah secara manual. Jalankan reconciliation private (dry-run lalai):

```bash
php app/reconcile_face_versions.php
php app/reconcile_face_versions.php --delete --grace-hours=24
```

Alat itu hanya mempertimbangkan direktori dua aras dengan ID/version selamat, tidak mengikuti/memadam symlink, melaporkan pointer dengan bukan enam baris, dan hanya membuang direktori versi yang tidak dirujuk metadata semasa serta melepasi grace period. Semak output/backup sebelum `--delete`.

Aliran kiosk:

1. `GET action=dataset` mengembalikan hanya imej versi aktif dan fingerprint kanonik `SHA-256(image_id:sha256|...)`.
2. Python mengesahkan hash fail, mengesan tepat satu muka per sampel, melapor validasi bersama fingerprint, dan hanya melatih profil cukup sampel.
3. Setiap acara attendance membawa fingerprint model terlatih. API mengunci murid/metadata semasa, mengira semula fingerprint, dan menyimpan fingerprint pada `kiosk_event`.
4. Fingerprint lapuk direkod sebagai event `rejected/stale_dataset` dan mendapat HTTP 409 tanpa mengubah snapshot. UUID duplicate mesti sepadan pada semua medan termasuk fingerprint; duplicate rejected kekal 409 dan duplicate accepted tidak menulis semula snapshot.
5. Ejen memaparkan arahan tekan **R** untuk sync selepas 409; ia tidak retry buta terhadap model lapuk.

`face_status` kembali `pending` selepas replacement dan hanya laporan kiosk untuk fingerprint semasa boleh menetapkan `ready`. Jalankan ejen dengan `APP_API_URL`, `KIOSK_API_KEY`, `KIOSK_ID`, dan pilihan kamera dalam `.env.example`. Cache kiosk ialah biometrik sensitif.

### Butang satu klik pada Windows localhost

Halaman **Kiosk Wajah** mempunyai butang **Mulakan Imbasan Wajah** untuk akaun `admin` dan `teacher`. Butang ini hanya membuka Python apabila pelayan PHP, projek, `.venv` dan webcam berada pada PC Windows interaktif yang sama. Ia tetap dimatikan secara lalai dan tidak boleh membuka kamera laptop pengguna daripada hosting awam.

Sediakan Python sekali dalam **VS Code PowerShell**, dengan terminal berada di folder projek:

```powershell
py -3.11 -m venv .venv
& ".\.venv\Scripts\python.exe" -m pip install -r ".\requirements.txt"
```

Untuk ujian satu PC, hentikan pelayan lama dengan `Ctrl+C`, kemudian mulakan PHP dari terminal yang sama dengan konfigurasi berikut:

```powershell
$env:APP_ENV = "development"
$env:APP_BASE_URL = "http://127.0.0.1:8000"
$env:LOGIN_RATE_LIMIT_SECRET = "use-a-local-random-secret-at-least-32-chars"
$env:KIOSK_API_KEY = "use-an-independent-local-key-at-least-24-chars"
$env:ALLOW_INSECURE_KIOSK_API = "true"
$env:ALLOW_LOCAL_KIOSK_LAUNCH = "true"
$env:LOCAL_KIOSK_ID = "same-pc-kiosk"
& "C:\xampp\php\php.exe" -S 127.0.0.1:8000
```

Buka `http://127.0.0.1:8000`, log masuk, pilih **Kiosk Wajah**, kemudian klik butang. Pelancar menolak semua permintaan production dan permintaan yang tidak datang terus daripada alamat loopback, walaupun flag tersalah diaktifkan. `scan.php` mengesahkan peranan, CSRF, Windows, `.venv`, Python 3.11, `cv2.face`, kunci dan URL; ia mewariskan konfigurasi kepada `run_face.bat` tanpa menulis secret ke fail. BAT sentiasa menggunakan `.venv\Scripts\python.exe`. Lock fail OS menghalang dua kamera kiosk berjalan serentak. Mesej web hanya mengesahkan arahan Windows dihantar; keputusan API, sync, latihan dan kamera sebenar dipaparkan dalam tetingkap Python. Tetingkap itu perlu ditutup dengan **Q**.

Jika laman dijalankan oleh Apache sebagai Windows service, proses service biasanya tidak dibenarkan memaparkan GUI pada desktop pengguna. Untuk butang satu klik localhost, gunakan PHP built-in server daripada VS Code seperti di atas. Pada domain/hosting, kekalkan `ALLOW_LOCAL_KIOSK_LAUNCH=false` dan jalankan ejen pada PC kamera secara manual atau melalui kaedah pengurusan peranti yang diluluskan.

## Kehadiran manual dan laporan

`attendance` kekal snapshot akhir satu murid/hari. Setiap perubahan manual kini memasukkan `manual_attendance_event` immutable dan mengemas kini snapshot dalam transaksi sama di bawah lock murid/baris. Acara menyimpan status/masa/sumber/aktor terdahulu, status/masa efektif baharu, masa tindakan DB, pengguna dan nota pilihan.

`attendance.time` ialah **masa kehadiran efektif**, bukan masa pembetulan. `Tidak Hadir` sentiasa mempunyai masa `NULL`; `Hadir` manual juga `NULL` jika masa sebenar tidak diberikan, termasuk rekod backdated. Dashboard/status/rekod melabelkannya sebagai masa efektif. Pembetulan kepada Hadir membatalkan outbox absence yang belum diproses.

## Telegram: web enqueue, CLI hantar

Dashboard dan `telegram.php` hanya memilih penerima layak secara terhad dan, dalam transaksi, mencipta `notification_log` serta `telegram_outbox` dengan `(source,dedupe_key)` unik. `update_id` digunakan untuk webhook; `notification_log_id` digunakan untuk absence. Tiada panggilan Telegram dalam permintaan Dashboard. Webhook juga hanya commit update/link/reply outbox dan kembali; ia tidak menjalankan batch rangkaian. Notifikasi gagal atau dibatalkan kerana status/pautan berubah boleh dimasukkan semula secara sengaja melalui butang Dashboard apabila murid kembali layak; rekod yang sudah `sent` tidak dihantar semula.

Worker CLI ialah satu-satunya penghantar. Ia memilih kerja mengikut `next_attempt_at,id` supaya retry tidak menyebabkan starvation, claim satu per satu, menyemak semula absence/chat sebelum send, dan mengemas kini outbox serta `notification_log` secara transaksi kepada sent/failed. Retry menggunakan backoff 30 saat hingga satu jam dan `TELEGRAM_OUTBOX_MAX_ATTEMPTS`. Dashboard membezakan belum digilir, queued, sent dan failed.

```bash
php /absolute/path/eattandence_tabika_kemas/app/telegram_worker.php 5
```

```cron
* * * * * /usr/bin/php /absolute/path/eattandence_tabika_kemas/app/telegram_worker.php 5 >> /private/log/eattendance-telegram.log 2>&1
```

Penghantaran ialah **at-least-once**: timeout selepas Telegram menerima mesej tetapi sebelum respons diterima adalah ambigu dan boleh menghasilkan duplicate. Jangan gunakan butang interaktif sebagai isyarat “sudah dihantar”; ia hanya bermaksud queued. Pantau outbox gagal/attempt maksimum.

## Naik taraf, operasi dan privasi

Ikut `MIGRATION.md`: downtime diperlukan untuk perubahan pointer/path muka dan deployment API+kiosk mesti diselaras. Deploy schema, pindah direktori legacy seperti dipetakan, dry-run reconciliation, deploy PHP dan Python, paksa sync/retrain semua kiosk, kemudian hidupkan worker/traffic.

Data biometrik kanak-kanak memerlukan persetujuan penjaga, retention/pemadaman terdokumen, backup disulitkan dan akses minimum. Sekat web ke `app/`, storan, SQL, Markdown, Python dan BAT. Uji pemulihan DB **bersama** storan wajah; lindungi cache kiosk dan rahsia environment.
