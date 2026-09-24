<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth();
$date = isset($_GET['date']) ? require_valid_date($_GET['date']) : date('Y-m-d');
$stmt = db()->prepare("SELECT s.student_id,s.name,s.class,a.status,a.time,a.source FROM student s LEFT JOIN attendance a ON a.student_id=s.student_id AND a.date=? ORDER BY FIELD(a.status,'Tidak Hadir','Hadir'),s.name");
$stmt->bind_param('s', $date);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
render_header('Status Kehadiran', 'Semua murid pada ' . date('d/m/Y', strtotime($date)));
?>
<div class="content-stack"><section class="card no-print"><form class="toolbar" method="get"><div class="field"><label for="date">Pilih tarikh</label><input class="input" id="date" name="date" type="date" value="<?= e($date) ?>" required></div><button class="btn btn-primary" type="submit">Papar status</button><button class="btn btn-secondary" type="button" data-print>Cetak</button></form></section>
<section class="card"><?php if (!$rows): render_empty('Tiada murid', 'Belum ada murid berdaftar.'); else: ?><div class="table-wrap"><table class="data-table responsive"><thead><tr><th>ID Murid</th><th>Nama</th><th>Kelas</th><th>Status</th><th>Masa efektif / Sumber</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td data-label="ID Murid"><?= e($row['student_id']) ?></td><td data-label="Nama"><?= e($row['name']) ?></td><td data-label="Kelas"><?= e($row['class']) ?></td><td data-label="Status"><?= status_badge($row['status']) ?></td><td data-label="Masa efektif / Sumber"><?= e($row['time'] ? date('h:i A', strtotime($row['time'])) : '—') ?><?= $row['source'] ? '<br><small class="muted">' . e($row['source'] === 'kiosk' ? 'Kiosk wajah' : 'Manual') . '</small>' : '' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></div>
<?php render_footer(); ?>
