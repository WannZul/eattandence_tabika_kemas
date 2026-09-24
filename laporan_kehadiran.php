<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth();
$month = trim((string) ($_GET['month'] ?? date('Y-m')));
if (!valid_month($month)) { throw new InvalidArgumentException('Bulan laporan tidak sah.'); }
$start = $month . '-01';
$end = (new DateTimeImmutable($start))->modify('first day of next month')->format('Y-m-d');
$stmt = db()->prepare("SELECT s.student_id,s.name,s.class,COUNT(a.id) jumlah_hadir FROM student s LEFT JOIN attendance a ON a.student_id=s.student_id AND a.status='Hadir' AND a.date>=? AND a.date<? GROUP BY s.student_id,s.name,s.class ORDER BY s.name");
$stmt->bind_param('ss',$start,$end);
$stmt->execute();
$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
render_header('Laporan Kehadiran Bulanan','Jumlah “Hadir” sahaja bagi ' . date('m/Y',strtotime($start)));
?>
<div class="content-stack"><section class="card no-print"><form class="toolbar" method="get"><div class="field"><label for="month">Pilih bulan</label><input class="input" id="month" name="month" type="month" value="<?= e($month) ?>" required></div><button class="btn btn-primary" type="submit">Jana laporan</button><button class="btn btn-secondary" type="button" data-print>Cetak</button></form></section><section class="card"><?php if(!$rows): render_empty('Tiada murid','Belum ada murid untuk dilaporkan.'); else: ?><div class="table-wrap"><table class="data-table responsive"><thead><tr><th>Bil.</th><th>ID Murid</th><th>Nama</th><th>Kelas</th><th>Jumlah Hadir</th></tr></thead><tbody><?php foreach($rows as $i=>$row): ?><tr><td data-label="Bil."><?= $i+1 ?></td><td data-label="ID Murid"><?= e($row['student_id']) ?></td><td data-label="Nama"><?= e($row['name']) ?></td><td data-label="Kelas"><?= e($row['class']) ?></td><td data-label="Jumlah Hadir"><strong><?= (int)$row['jumlah_hadir'] ?> hari</strong></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></div>
<?php render_footer(); ?>
