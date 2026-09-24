<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/telegram_service.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    throw new UserFacingException('Notifikasi mesti dimasukkan ke baris gilir melalui Dashboard.', 405);
}
require_csrf();
$date = require_valid_date($_POST['date'] ?? '');
if (env_value('TELEGRAM_BOT_TOKEN') === null) {
    flash('error', 'TELEGRAM_BOT_TOKEN belum dikonfigurasi.');
    redirect('dashboard.php?date=' . rawurlencode($date));
}

$batchSize = telegram_batch_size();
$queued = 0;
$connection = db();
$connection->begin_transaction();
try {
    // New recipients are queued once. Cancelled or exhausted failed jobs may be
    // deliberately reset by another confirmed Dashboard submission.
    $stmt = $connection->prepare(
        "SELECT a.student_id,s.name,s.telegram_chat_id,n.id notification_id,o.id outbox_id
         FROM attendance a
         JOIN student s ON s.student_id=a.student_id
         LEFT JOIN notification_log n
           ON n.student_id=a.student_id AND n.attendance_date=a.date AND n.notification_type='absence'
         LEFT JOIN telegram_outbox o
           ON o.notification_log_id=n.id AND o.source='absence'
         WHERE a.date=? AND a.status='Tidak Hadir'
           AND s.telegram_chat_id IS NOT NULL AND s.telegram_chat_id<>''
           AND (
                n.id IS NULL
                OR (n.status IN ('failed','cancelled') AND (o.id IS NULL OR o.status IN ('failed','cancelled')))
           )
         ORDER BY s.name,a.student_id
         LIMIT ? FOR UPDATE"
    );
    $stmt->bind_param('si', $date, $batchSize);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($students as $student) {
        $studentId = (string) $student['student_id'];
        $chatId = (string) $student['telegram_chat_id'];
        $type = 'absence';
        $message = "eAttendance TABIKA KEMAS\n\nAssalamualaikum.\n\nDimaklumkan bahawa anak/jagaan anda:\nNama: {$student['name']}\nID Murid: {$studentId}\nTarikh: "
            . date('d/m/Y', strtotime($date))
            . "\nStatus: Tidak Hadir\n\nSila hubungi pihak TABIKA KEMAS jika maklumat ini perlu diperbetulkan. Terima kasih.";
        $source = 'absence';
        $dedupe = 'absence:' . $studentId . ':' . $date;
        $notificationId = (int) ($student['notification_id'] ?? 0);
        $outboxId = (int) ($student['outbox_id'] ?? 0);

        if ($notificationId === 0) {
            $log = $connection->prepare("INSERT INTO notification_log(student_id,attendance_date,notification_type,telegram_chat_id,status,attempt_count) VALUES(?,?,?,?,'pending',0)");
            $log->bind_param('ssss', $studentId, $date, $type, $chatId);
            $log->execute();
            $notificationId = (int) $connection->insert_id;
        } else {
            $resetLog = $connection->prepare("UPDATE notification_log SET telegram_chat_id=?,status='pending',telegram_message_id=NULL,error_message=NULL,attempt_count=0,attempted_at=NULL,sent_at=NULL WHERE id=? AND status IN ('failed','cancelled')");
            $resetLog->bind_param('si', $chatId, $notificationId);
            $resetLog->execute();
            if ($resetLog->affected_rows !== 1) {
                continue;
            }
        }

        if ($outboxId === 0) {
            $outbox = $connection->prepare("INSERT INTO telegram_outbox(source,dedupe_key,update_id,notification_log_id,telegram_chat_id,message_text,status,next_attempt_at) VALUES(?,?,NULL,?,?,?,'pending',NOW())");
            $outbox->bind_param('ssiss', $source, $dedupe, $notificationId, $chatId, $message);
            $outbox->execute();
        } else {
            $resetOutbox = $connection->prepare("UPDATE telegram_outbox SET telegram_chat_id=?,message_text=?,status='pending',attempt_count=0,next_attempt_at=NOW(),locked_at=NULL,last_error=NULL,telegram_message_id=NULL,sent_at=NULL WHERE id=? AND source='absence' AND status IN ('failed','cancelled')");
            $resetOutbox->bind_param('ssi', $chatId, $message, $outboxId);
            $resetOutbox->execute();
            if ($resetOutbox->affected_rows !== 1) {
                throw new RuntimeException('Absence outbox reset lost its expected state.');
            }
        }
        $queued++;
    }
    $connection->commit();
} catch (Throwable $exception) {
    try { $connection->rollback(); } catch (Throwable) { }
    error_log('Absence enqueue failed: ' . $exception->getMessage());
    flash('error', 'Notifikasi tidak dapat dimasukkan ke baris gilir.');
    redirect('dashboard.php?date=' . rawurlencode($date));
}

$remainingStmt = db()->prepare(
    "SELECT COUNT(*) total
     FROM attendance a
     JOIN student s ON s.student_id=a.student_id
     LEFT JOIN notification_log n
       ON n.student_id=a.student_id AND n.attendance_date=a.date AND n.notification_type='absence'
     LEFT JOIN telegram_outbox o
       ON o.notification_log_id=n.id AND o.source='absence'
     WHERE a.date=? AND a.status='Tidak Hadir'
       AND s.telegram_chat_id IS NOT NULL AND s.telegram_chat_id<>''
       AND (n.id IS NULL OR (n.status IN ('failed','cancelled') AND (o.id IS NULL OR o.status IN ('failed','cancelled'))))"
);
$remainingStmt->bind_param('s', $date);
$remainingStmt->execute();
$remaining = (int) ($remainingStmt->get_result()->fetch_assoc()['total'] ?? 0);

if ($queued === 0 && $remaining === 0) {
    flash('info', 'Tiada notifikasi baharu atau gagal yang layak dimasukkan semula ke baris gilir.');
} else {
    flash(
        $remaining > 0 ? 'warning' : 'success',
        "{$queued} notifikasi dimasukkan ke baris gilir. Baki belum digilir: {$remaining}. Worker CLI akan menghantar tanpa menunggu permintaan web."
    );
}
redirect('dashboard.php?date=' . rawurlencode($date));
