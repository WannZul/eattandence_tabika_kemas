<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth();

$date = isset($_GET['date']) ? require_valid_date($_GET['date']) : date('Y-m-d');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();
        $studentId = require_student_id($_POST['student_id'] ?? '');
        $attendanceDate = require_valid_date($_POST['attendance_date'] ?? '');
        $status = require_status($_POST['status'] ?? '');
        $effectiveInput = trim((string) ($_POST['effective_time'] ?? ''));
        $effectiveTime = null;
        if ($status === 'Hadir' && $effectiveInput !== '') {
            $parsed = DateTimeImmutable::createFromFormat('!H:i', $effectiveInput);
            if ($parsed === false || $parsed->format('H:i') !== $effectiveInput) {
                throw new InvalidArgumentException('Masa hadir sebenar tidak sah.');
            }
            $effectiveTime = $parsed->format('H:i:s');
        }
        $noteInput = trim((string) ($_POST['correction_note'] ?? ''));
        $note = $noteInput === '' ? null : require_text($noteInput, 'Nota pembetulan', 2, 255);
        $user = current_user();
        $connection = db();
        $connection->begin_transaction();
        try {
            $studentStmt = $connection->prepare('SELECT name FROM student WHERE student_id=? FOR UPDATE');
            $studentStmt->bind_param('s', $studentId);
            $studentStmt->execute();
            $student = $studentStmt->get_result()->fetch_assoc();
            if (!$student) { throw new InvalidArgumentException('Murid tidak dijumpai.'); }
            $snapshotStmt = $connection->prepare('SELECT * FROM attendance WHERE student_id=? AND date=? FOR UPDATE');
            $snapshotStmt->bind_param('ss', $studentId, $attendanceDate);
            $snapshotStmt->execute();
            $prior = $snapshotStmt->get_result()->fetch_assoc();
            $name = (string) $student['name'];
            $source = 'manual';
            $userId = (int) $user['id'];
            if ($prior) {
                $attendanceId = (int) $prior['id'];
                $update = $connection->prepare("UPDATE attendance SET name=?,time=?,status=?,source='manual',recorded_by=?,observed_at=NULL,kiosk_id=NULL,recognition_confidence=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $update->bind_param('sssii', $name, $effectiveTime, $status, $userId, $attendanceId);
                $update->execute();
            } else {
                $insert = $connection->prepare("INSERT INTO attendance(student_id,name,date,time,status,source,recorded_by,observed_at,kiosk_id,recognition_confidence) VALUES(?,?,?,?,?,'manual',?,NULL,NULL,NULL)");
                $insert->bind_param('sssssi', $studentId, $name, $attendanceDate, $effectiveTime, $status, $userId);
                $insert->execute();
                $attendanceId = (int) $connection->insert_id;
            }
            $priorStatus = $prior['status'] ?? null;
            $priorTime = $prior['time'] ?? null;
            $priorSource = $prior['source'] ?? null;
            $priorActor = isset($prior['recorded_by']) ? (int) $prior['recorded_by'] : null;
            $event = $connection->prepare('INSERT INTO manual_attendance_event(attendance_id,student_id,attendance_date,prior_status,prior_effective_time,prior_source,prior_recorded_by,new_status,new_effective_time,acting_user_id,acting_username,reason_note) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
            $event->bind_param('isssssississ', $attendanceId, $studentId, $attendanceDate, $priorStatus, $priorTime, $priorSource, $priorActor, $status, $effectiveTime, $userId, $user['username'], $note);
            $event->execute();
            if ($status === 'Hadir') {
                $cancel = $connection->prepare("UPDATE telegram_outbox o JOIN notification_log n ON n.id=o.notification_log_id SET o.status='cancelled',o.locked_at=NULL,o.last_error='Attendance corrected to present',n.status='cancelled',n.error_message='Attendance corrected to present' WHERE n.student_id=? AND n.attendance_date=? AND n.notification_type='absence' AND o.source='absence' AND o.status IN ('pending','failed')");
                $cancel->bind_param('ss', $studentId, $attendanceDate);
                $cancel->execute();
            }
            $connection->commit();
            flash('success', 'Kehadiran ' . $name . ' disimpan bersama acara audit yang tidak boleh diubah.');
        } catch (Throwable $exception) {
            try { $connection->rollback(); } catch (Throwable) { }
            throw $exception;
        }
    } catch (InvalidArgumentException|UserFacingException $exception) {
        flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('Manual attendance update failed: ' . $exception->getMessage());
        flash('error', 'Kehadiran manual tidak dapat disimpan.');
    }
    redirect('manual_kehadiran.php?date=' . rawurlencode($attendanceDate ?? $date));
}

$stmt = db()->prepare('SELECT s.student_id,s.name,s.class,a.status,a.time FROM student s LEFT JOIN attendance a ON a.student_id=s.student_id AND a.date=? ORDER BY s.name,s.student_id');
$stmt->bind_param('s', $date); $stmt->execute(); $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$auditStmt = db()->prepare('SELECT e.student_id,s.name,e.prior_status,e.new_status,e.new_effective_time,e.acting_username,e.reason_note,e.action_at FROM manual_attendance_event e JOIN student s ON s.student_id=e.student_id WHERE e.attendance_date=? ORDER BY e.action_at DESC,e.id DESC LIMIT 20');
$auditStmt->bind_param('s', $date); $auditStmt->execute(); $auditEvents = $auditStmt->get_result()->fetch_all(MYSQLI_ASSOC);
render_header('Kehadiran Manual', 'Status harian dan jejak pembetulan untuk ' . date('d/m/Y', strtotime($date)));
?>
<div class="content-stack">
<section class="card no-print"><form class="toolbar" method="get"><div class="field"><label for="date">Tarikh kehadiran</label><input class="input" type="date" id="date" name="date" value="<?= e($date) ?>" required></div><button class="btn btn-primary" type="submit">Papar murid</button></form></section>
<section class="alert alert-info"><span>Masa ialah masa hadir sebenar, bukan masa borang disimpan. Biarkan kosong jika tidak diketahui. Status Tidak Hadir sentiasa menyimpan masa sebagai kosong.</span></section>
<section class="card"><?php if (!$students): render_empty('Tiada murid', 'Daftar murid terlebih dahulu.'); else: ?><div class="table-wrap"><table class="data-table responsive"><thead><tr><th>Bil.</th><th>ID Murid</th><th>Nama / Kelas</th><th>Status semasa</th><th>Pembetulan</th></tr></thead><tbody><?php foreach ($students as $index=>$student): ?><tr><td data-label="Bil."><?= $index+1 ?></td><td data-label="ID Murid"><?= e($student['student_id']) ?></td><td data-label="Nama / Kelas"><strong><?= e($student['name']) ?></strong><br><small class="muted"><?= e($student['class']) ?></small></td><td data-label="Status semasa"><?= status_badge($student['status']) ?><?= $student['time'] ? '<br><small class="muted">Masa efektif ' . e(date('h:i A',strtotime($student['time']))) . '</small>' : '' ?></td><td data-label="Pembetulan"><form class="grid" method="post" action="manual_kehadiran.php?date=<?= e($date) ?>"><?= csrf_field() ?><input type="hidden" name="student_id" value="<?= e($student['student_id']) ?>"><input type="hidden" name="attendance_date" value="<?= e($date) ?>"><div class="toolbar"><div class="field"><label for="status-<?= e($student['student_id']) ?>">Status</label><select id="status-<?= e($student['student_id']) ?>" name="status" required><option value="">Pilih</option><option value="Hadir" <?= $student['status']==='Hadir'?'selected':'' ?>>Hadir</option><option value="Tidak Hadir" <?= $student['status']==='Tidak Hadir'?'selected':'' ?>>Tidak Hadir</option></select></div><div class="field"><label for="time-<?= e($student['student_id']) ?>">Masa hadir sebenar (pilihan)</label><input class="input" id="time-<?= e($student['student_id']) ?>" type="time" name="effective_time" value="<?= e($student['status']==='Hadir'&&$student['time']?substr($student['time'],0,5):'') ?>"></div></div><div class="field"><label for="note-<?= e($student['student_id']) ?>">Nota pembetulan (pilihan)</label><input class="input" id="note-<?= e($student['student_id']) ?>" name="correction_note" maxlength="255" placeholder="Sebab ringkas"></div><div><button class="btn btn-primary" type="submit">Simpan</button></div></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<section class="card"><h2>Audit pembetulan terkini</h2><?php if (!$auditEvents): render_empty('Belum ada pembetulan manual', 'Setiap perubahan manual akan direkodkan di sini.'); else: ?><div class="table-wrap"><table class="data-table responsive"><thead><tr><th>Masa tindakan</th><th>Murid</th><th>Perubahan</th><th>Masa efektif baharu</th><th>Pengguna / Nota</th></tr></thead><tbody><?php foreach($auditEvents as $event): ?><tr><td data-label="Masa tindakan"><?= e(date('d/m/Y h:i:s A',strtotime($event['action_at']))) ?></td><td data-label="Murid"><?= e($event['name']) ?> (<?= e($event['student_id']) ?>)</td><td data-label="Perubahan"><?= e(($event['prior_status']??'Belum ditanda').' → '.$event['new_status']) ?></td><td data-label="Masa efektif"><?= e($event['new_effective_time']?date('h:i A',strtotime($event['new_effective_time'])):'—') ?></td><td data-label="Pengguna / Nota"><?= e($event['acting_username']) ?><?= $event['reason_note']?'<br><small class="muted">'.e($event['reason_note']).'</small>':'' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</div>
<?php render_footer(); ?>
