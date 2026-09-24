# Migrasi production

`attendance.sql` ialah skema clean-install yang destruktif. Jangan importnya ke DB berdata. `production_upgrade.sql` menyasarkan satu baseline khusus: skema production-modernization pra-REVIEW yang **sudah** mempunyai `login_attempt`, medan validasi `student.face_status/face_validated_at/face_validation_message/usable_face_samples`, dan `telegram_outbox` webhook-only dengan constraint/index asalnya. Ia belum mempunyai mana-mana pembaikan baharu dalam panduan ini.

DDL MySQL/MariaDB auto-commit dan sintaks `ADD ... IF NOT EXISTS` tidak konsisten antara versi sasaran. Oleh itu skrip sengaja **sekali sahaja**, bukan migrasi idempotent. Jika fail lama pernah dijalankan sepenuhnya/separuh atau baseline anda belum mempunyai objek pra-REVIEW tersebut, inventori `INFORMATION_SCHEMA`, pulihkan backup jika sesuai, atau ekstrak hanya pernyataan yang disemak DBA. Jangan rerun membuta tuli. MySQL 8/MariaDB 10.4 perlu menguatkuasakan InnoDB foreign key dan CHECK constraint.

## Urutan naik taraf terkawal

1. Hentikan web write, kiosk, webhook dan semua worker. Ambil serta uji backup DB dan seluruh `PRIVATE_STORAGE_PATH` pada checkpoint sama.
2. Sahkan baseline/jadual/constraint. Catat jumlah users, students, enam imej semasa setiap murid, attendance, kiosk events, notification logs dan update logs.
3. Import `production_upgrade.sql` sekali. Ia menambah credential lifecycle, limiter pair index, pointer/version muka, fingerprint/outcome kiosk, audit manual, dan outbox generalized. Historical `kiosk_event.dataset_fingerprint` sengaja `NULL`; jangan isi fingerprint semasa secara palsu. Historical snapshot juga tidak dijadikan acara audit rekaan.
4. Skrip memberi versi legacy deterministik `legacy-<24 hex>` dan mengubah metadata path. Semasa downtime, untuk setiap murid dengan pointer, cipta `faces/<ID>/<face_dataset_version>/`, kemudian pindahkan **hanya** `01.jpg` hingga `06.jpg` daripada `faces/<ID>/` ke folder itu. Jangan deploy PHP sebelum semua path DB dan fail sepadan. Contoh pemetaan:

```sql
SELECT student_id,face_dataset_version,
       CONCAT('faces/',student_id,'/',face_dataset_version,'/') AS target_directory
FROM student WHERE face_dataset_version IS NOT NULL ORDER BY student_id;
```

5. Sahkan enam baris semasa dan hash fail. Jalankan `php app/reconcile_face_versions.php` dalam dry-run. `CURRENT_METADATA_INCOMPLETE`, `UNSAFE_OR_LEGACY`, atau fail/hash yang tidak sepadan mesti diselesaikan sebelum traffic. Jangan gunakan `--delete` semasa migrasi awal.
6. Semak legacy manual absences. Versi lama mengisi jam tindakan sebagai `attendance.time`; selepas backup dan semakan perniagaan, kosongkan hanya rekod yang diketahui terjejas:

```sql
UPDATE attendance SET time=NULL WHERE source='manual' AND status='Tidak Hadir';
```

7. Tetapkan environment baharu: `LOGIN_PAIR_MAX_ATTEMPTS=5`, `LOGIN_USERNAME_MAX_ATTEMPTS=20`, `LOGIN_NETWORK_MAX_ATTEMPTS=30`, secret login minimum 32 aksara, `TELEGRAM_ENQUEUE_BATCH_SIZE`, worker limits, `APP_BASE_URL`, `SESSION_NAME`, dan `PUBLIC_DOCUMENT_ROOT` kanonik. Pastikan `PRIVATE_STORAGE_PATH` berada di luar public root serta direktori aplikasi; parent path mesti wujud. Path cookie berubah mengikut mount; pengguna mungkin perlu login semula.
8. Deploy PHP/API dan ejen Python 3.11 sebagai satu compatibility window semasa traffic masih berhenti. API baharu mewajibkan `dataset_fingerprint`; kiosk lama akan ditolak. Paksa setiap kiosk sync/retrain dan pastikan validation fingerprint semasa menetapkan profil kepada ready.
9. Jalankan worker secara manual, kemudian aktifkan cron. Dashboard hanya queue; hanya worker membuat rangkaian Telegram. Aktifkan webhook, kiosk dan web writes selepas smoke check.
10. Simpan backup sehingga semakan selesai. Jalankan reconciliation dry-run berkala; gunakan `--delete --grace-hours=24` hanya selepas output/retention diluluskan.

## Semakan pascamigrasi

```sql
SELECT s.student_id,s.face_dataset_version,COUNT(f.id) AS current_images
FROM student s LEFT JOIN face_image f
 ON f.student_id=s.student_id AND f.dataset_version=s.face_dataset_version
GROUP BY s.student_id,s.face_dataset_version
HAVING s.face_dataset_version IS NOT NULL AND COUNT(f.id)<>6;

SELECT COUNT(*) AS historical_unknown_fingerprint
FROM kiosk_event WHERE dataset_fingerprint IS NULL;

SELECT outcome,rejection_reason,COUNT(*)
FROM kiosk_event GROUP BY outcome,rejection_reason;

SELECT source,status,COUNT(*) FROM telegram_outbox GROUP BY source,status;
SELECT status,COUNT(*) FROM notification_log GROUP BY status;
```

Uji login unknown/known secara generik, reset yang membatalkan sesi lama, forced account change, create/replace muka, stale kiosk 409 + duplicate, pembetulan manual dengan audit, queue absence, worker success/failure, webhook duplicate, subdirectory cookie, JSON 401/403 enrollment dan mobile menu pendek.

## Migrasi daripada prototaip lama

Lebih selamat cipta DB baharu dan import `attendance.sql`. Cipta admin pertama tanpa memindah kata laluan plaintext. Normalisasi status kepada `Hadir`/`Tidak Hadir`, dedupe `(student_id,date)`, dan jangan cipta history/aktor yang tidak diketahui.

Untuk setiap dataset legacy, jana versi selamat (32 hex disyorkan), letak enam JPEG terus di `PRIVATE_STORAGE_PATH/faces/<ID>/<version>/01..06.jpg`, kira SHA-256/dimensi, masukkan `face_image.dataset_version`, dan set `student.face_dataset_version` kepada versi sama dalam transaksi. Biarkan status pending; hanya kiosk boleh validate. Attendance lama `Tidak Hadir` mesti menggunakan `time=NULL`; kehadiran lama dengan masa tidak diketahui juga `NULL`.

Jangan tandakan event import sebagai bukti kiosk, jangan fabrikasi fingerprint, dan jangan salin token/secret lama. Bandingkan jumlah murid, snapshot unik dan laporan bulanan sebelum menukar DB production.

## Rollback/caveat

Pointer DB dan fail ialah satu pasangan backup. Selepas DDL/path migration, rollback kod sahaja tidak mencukupi. Pulihkan DB **dan** storan daripada checkpoint sama. Telegram adalah at-least-once; timeout remote ambigu tidak boleh diselesaikan oleh rollback DB. Reconciliation tidak pernah memilih versi lama sebagai current dan tidak memadam symlink/laluan luar pola selamat.
