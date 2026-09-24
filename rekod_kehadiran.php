<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth();

$date = trim((string) ($_GET['date'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$query = trim((string) ($_GET['q'] ?? ''));
if ($date !== '') { $date = require_valid_date($date); }
if ($status !== '' && !in_array($status, ['Hadir','Tidak Hadir'], true)) { throw new InvalidArgumentException('Tapis status tidak sah.'); }
if (strlen($query) > 100) { throw new InvalidArgumentException('Carian terlalu panjang.'); }
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$like = '%' . $query . '%';

$countStmt = db()->prepare("SELECT COUNT(*) total FROM attendance a JOIN student s ON s.student_id=a.student_id WHERE (?='' OR a.date=?) AND (?='' OR a.status=?) AND (?='' OR s.name LIKE ? OR s.student_id LIKE ?)");
$countStmt->bind_param('sssssss', $date, $date, $status, $status, $query, $like, $like);
$countStmt->execute();
$total = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }
$stmt = db()->prepare("SELECT a.id,a.student_id,s.name,s.class,a.date,a.time,a.status,a.source FROM attendance a JOIN student s ON s.student_id=a.student_id WHERE (?='' OR a.date=?) AND (?='' OR a.status=?) AND (?='' OR s.name LIKE ? OR s.student_id LIKE ?) ORDER BY a.date DESC,COALESCE(a.time,'00:00:00') DESC,a.id DESC LIMIT ? OFFSET ?");
$stmt->bind_param('sssssssii', $date, $date, $status, $status, $query, $like, $like, $perPage, $offset);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function records_url(int $targetPage, string $date, string $status, string $query): string { return 'rekod_kehadiran.php?' . http_build_query(array_filter(['date'=>$date,'status'=>$status,'q'=>$query,'page'=>$targetPage], static fn($v)=>$v!=='')); }
render_header('Rekod Kehadiran', $total . ' rekod sepadan');
?>
<div class="content-stack"><section class="card no-print"><form class="toolbar" method="get"><div class="field"><label for="q">Cari murid</label><input class="input" id="q" name="q" value="<?= e($query) ?>" maxlength="100" placeholder="Nama atau ID"></div><div class="field"><label for="date">Tarikh</label><input class="input" id="date" name="date" type="date" value="<?= e($date) ?>"></div><div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Semua status</option><option value="Hadir" <?= $status==='Hadir'?'selected':'' ?>>Hadir</option><option value="Tidak Hadir" <?= $status==='Tidak Hadir'?'selected':'' ?>>Tidak Hadir</option></select></div><button class="btn btn-primary" type="submit">Tapis</button><a class="btn btn-secondary" href="rekod_kehadiran.php">Kosongkan</a></form></section>
<section class="card"><?php if (!$rows): render_empty('Tiada rekod dijumpai', 'Ubah penapis atau rekodkan kehadiran baharu.'); else: ?><div class="table-wrap"><table class="data-table responsive"><thead><tr><th>Tarikh</th><th>ID Murid</th><th>Nama / Kelas</th><th>Masa efektif</th><th>Sumber</th><th>Status</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td data-label="Tarikh"><?= e(date('d/m/Y', strtotime($row['date']))) ?></td><td data-label="ID Murid"><?= e($row['student_id']) ?></td><td data-label="Nama / Kelas"><strong><?= e($row['name']) ?></strong><br><small class="muted"><?= e($row['class']) ?></small></td><td data-label="Masa efektif"><?= e($row['time'] ? date('h:i:s A', strtotime($row['time'])) : '—') ?></td><td data-label="Sumber"><?= e($row['source']==='kiosk'?'Kiosk wajah':'Manual') ?></td><td data-label="Status"><?= status_badge($row['status']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php if ($totalPages>1): ?><nav class="pagination no-print" aria-label="Halaman rekod"><?php if ($page>1): ?><a href="<?= e(records_url($page-1,$date,$status,$query)) ?>">Sebelum</a><?php endif; ?><span class="current">Halaman <?= $page ?> / <?= $totalPages ?></span><?php if ($page<$totalPages): ?><a href="<?= e(records_url($page+1,$date,$status,$query)) ?>">Seterusnya</a><?php endif; ?></nav><?php endif; ?><?php endif; ?></section></div>
<?php render_footer(); ?>
