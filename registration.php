<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth(['admin']);
$registeredStudents = db()->query('SELECT student_id,name,class,face_status,face_validated_at,face_validation_message,usable_face_samples,telegram_chat_id FROM student ORDER BY name')->fetch_all(MYSQLI_ASSOC);
render_header('Daftar Murid', 'Daftar murid baharu atau ambil semula enam imej tanpa memadam rekod sedia ada');
?>
<div class="content-stack" id="registrationApp" data-csrf="<?= e(csrf_token()) ?>">
<section class="alert alert-info"><span>Kamera pelayar hanya membuat semakan kualiti awal. Selepas imej baharu disimpan, profil berstatus <strong>Menunggu validasi</strong> sehingga kiosk mengesan sekurang-kurangnya bilangan sampel boleh guna yang dikonfigurasi. Profil pending/tidak sah tidak digunakan untuk pengecaman.</span></section>
<section class="card camera-panel">
<div>
    <div id="replacementMode" class="alert alert-warning" role="status" hidden>
        <span><strong>Mod penggantian imej.</strong> <span id="replacementSummary"></span> ID, nama dan kelas dikunci. Rekod kehadiran, notis dan pautan Telegram murid ini tidak akan dipadam.</span>
    </div>
    <div class="grid grid-2">
        <div class="field"><label for="studentId">ID Murid</label><input class="input" id="studentId" maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9_-]{1,29}" placeholder="Contoh: M001" required></div>
        <div class="field"><label for="studentClass">Kelas</label><input class="input" id="studentClass" maxlength="50" placeholder="Contoh: TABIKA A" required></div>
    </div>
    <div class="field"><label for="studentName">Nama Murid</label><input class="input" id="studentName" maxlength="100" placeholder="Nama penuh murid" required></div>
    <div class="field"><label for="cameraSelect">Pilih kamera</label><select id="cameraSelect"><option value="">Kamera automatik</option></select><small>Semua kamera yang dibenarkan boleh digunakan; tiada sekatan jenama.</small></div>
    <div class="video-frame"><video id="cameraVideo" autoplay playsinline muted></video><div class="camera-guide" aria-hidden="true"></div></div>
    <p id="cameraStatus" class="help-text" role="status">Kamera belum dibuka.</p>
    <div class="button-row"><button class="btn btn-primary" type="button" id="startCamera">Buka kamera</button><button class="btn btn-warning" type="button" id="captureImage" disabled>Ambil gambar</button><button class="btn btn-secondary" type="button" id="stopCamera">Tutup kamera</button></div>
</div>
<aside>
    <h2 id="captureHeading">6 sudut wajah</h2><p class="muted">Pastikan hanya satu muka, cahaya mencukupi, muka jelas dan memenuhi panduan bujur.</p>
    <div class="progress" aria-label="Kemajuan gambar"><span id="captureProgress"></span></div><p id="captureCount" class="help-text">0 daripada 6 gambar</p>
    <div class="preview-grid" id="previewGrid" aria-live="polite"></div>
    <div id="replaceConfirmationArea" class="field" hidden>
        <label><input type="checkbox" id="replaceConfirmation"> <span id="replaceConfirmationText">Saya mengesahkan enam imej baharu ini akan menggantikan imej wajah murid yang dipilih.</span></label>
        <small>Selepas simpanan, pengecaman dinyahaktifkan sehingga kiosk menyegerak dan memvalidasi imej baharu.</small>
    </div>
    <div id="registrationMessage" role="status"></div>
    <div class="button-row"><button class="btn btn-secondary" type="button" id="resetImages">Kosongkan tangkapan</button><button class="btn btn-primary" type="button" id="saveStudent" disabled>Simpan murid</button><button class="btn btn-secondary" type="button" id="cancelReplacement" hidden>Batal penggantian</button></div>
</aside>
</section>
<section class="card"><h2>Murid berdaftar & Telegram</h2><p class="muted">Gunakan <strong>Ambil semula imej</strong> untuk menggantikan enam imej profil pada murid yang sama tanpa membuat murid pendua atau memadam sejarah. Jalankan sync kiosk selepas simpanan.</p>
<?php if (!$registeredStudents): render_empty('Belum ada murid', 'Murid yang disimpan akan muncul di sini sementara menunggu validasi kiosk.'); else: ?><div class="table-wrap"><table class="data-table responsive"><thead><tr><th>ID Murid</th><th>Nama / Kelas</th><th>Wajah</th><th>Telegram</th><th></th></tr></thead><tbody><?php foreach ($registeredStudents as $student): ?><tr><td data-label="ID Murid"><?= e($student['student_id']) ?></td><td data-label="Nama / Kelas"><strong><?= e($student['name']) ?></strong><br><small class="muted"><?= e($student['class']) ?></small></td><td data-label="Wajah"><?= face_status_badge($student['face_status']) ?><br><small class="muted"><?= e((string) ($student['face_validation_message'] ?: ($student['face_status'] === 'pending' ? 'Belum disemak oleh kiosk.' : 'Tiada butiran validasi.'))) ?></small></td><td data-label="Telegram"><?= $student['telegram_chat_id'] ? '<span class="badge badge-success">Dipautkan</span>' : '<span class="badge badge-muted">Belum dipautkan</span>' ?></td><td data-label="Tindakan"><div class="button-row"><button class="btn btn-warning replace-face-images" type="button" data-student-id="<?= e($student['student_id']) ?>" data-student-name="<?= e($student['name']) ?>" data-student-class="<?= e($student['class']) ?>" data-face-status="<?= e($student['face_status']) ?>">Ambil semula imej</button><a class="btn btn-secondary" href="connect_telegram.php?student_id=<?= rawurlencode($student['student_id']) ?>">Urus pautan</a></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section></div>
<script src="assets/js/registration.js" defer></script>
<?php render_footer(); ?>
