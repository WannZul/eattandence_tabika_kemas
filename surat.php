<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth();
$id=require_positive_int($_GET['id']??null,'ID notis');
$stmt=db()->prepare('SELECT n.id,n.absence_date,n.reason,n.created_at,s.student_id,s.name,s.class FROM absence_notice n JOIN student s ON s.student_id=n.student_id WHERE n.id=? LIMIT 1');$stmt->bind_param('i',$id);$stmt->execute();$notice=$stmt->get_result()->fetch_assoc();
if(!$notice){http_response_code(404);throw new RuntimeException('Notis tidak dijumpai.');}
render_header('Surat Ketidakhadiran','Notis #' . $id);
?>
<div class="content-stack"><div class="button-row no-print"><button class="btn btn-primary" type="button" data-print>Cetak / Simpan PDF</button><a class="btn btn-secondary" href="notice.php">Kembali ke notis</a></div><article class="notice-paper"><header><div class="brand-mark large">K</div><h1>TABIKA KEMAS</h1><p>Sistem eAttendance</p></header><p class="text-right">Tarikh surat: <?= e(date('d/m/Y',strtotime($notice['created_at']))) ?></p><div class="letter-body"><p>Kepada,<br><strong>Guru TABIKA KEMAS</strong></p><p>Tuan/Puan,</p><h2>MAKLUMAN KETIDAKHADIRAN MURID</h2><p>Dengan segala hormatnya perkara di atas adalah dirujuk.</p><p>Dimaklumkan bahawa anak/jagaan saya, <strong><?= e($notice['name']) ?></strong> (ID: <?= e($notice['student_id']) ?>, Kelas: <?= e($notice['class']) ?>), tidak dapat hadir ke TABIKA KEMAS pada <strong><?= e(date('d/m/Y',strtotime($notice['absence_date']))) ?></strong> atas sebab berikut:</p><p><strong><?= nl2br(e($notice['reason'])) ?></strong></p><p>Sehubungan dengan itu, saya memohon pihak TABIKA KEMAS mengambil maklum ketidakhadiran tersebut.</p><p>Sekian, terima kasih.</p><p class="signature">Yang benar,<br><br><br>______________________________<br>Ibu/Bapa/Penjaga</p></div></article></div>
<?php render_footer(); ?>
