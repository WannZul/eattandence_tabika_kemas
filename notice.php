<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_csrf();
    $studentId=require_student_id($_POST['student_id']??'');
    $date=require_valid_date($_POST['absence_date']??'','Tarikh tidak hadir');
    $reason=require_text($_POST['reason']??'','Sebab ketidakhadiran',3,1000);
    $check=db()->prepare('SELECT 1 FROM student WHERE student_id=?');$check->bind_param('s',$studentId);$check->execute();
    if(!$check->get_result()->fetch_row()){throw new InvalidArgumentException('Murid tidak dijumpai.');}
    $userId=current_user()['id'];
    $stmt=db()->prepare('INSERT INTO absence_notice(student_id,absence_date,reason,created_by) VALUES(?,?,?,?)');$stmt->bind_param('sssi',$studentId,$date,$reason,$userId);$stmt->execute();
    flash('success','Notis ketidakhadiran berjaya disimpan.');redirect('surat.php?id=' . $stmt->insert_id);
}
$students=db()->query('SELECT student_id,name,class FROM student ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$notices=db()->query('SELECT n.id,n.absence_date,n.reason,n.created_at,s.student_id,s.name FROM absence_notice n JOIN student s ON s.student_id=n.student_id ORDER BY n.created_at DESC,n.id DESC LIMIT 30')->fetch_all(MYSQLI_ASSOC);
render_header('Notis Ketidakhadiran','Simpan sebab ketidakhadiran dan cetak surat makluman');
?>
<div class="content-stack"><section class="grid grid-2"><article class="card"><h2>Notis baharu</h2><?php if(!$students): ?><div class="alert alert-warning"><span>Daftar murid sebelum menyediakan notis.</span></div><?php else: ?><form method="post" class="content-stack"><?= csrf_field() ?><div class="field"><label for="student_id">Nama murid</label><select id="student_id" name="student_id" required><option value="">Pilih murid</option><?php foreach($students as $s): ?><option value="<?= e($s['student_id']) ?>"><?= e($s['name'].' · '.$s['student_id'].' · '.$s['class']) ?></option><?php endforeach; ?></select></div><div class="field"><label for="absence_date">Tarikh tidak hadir</label><input class="input" type="date" id="absence_date" name="absence_date" max="<?= e(date('Y-m-d')) ?>" required></div><div class="field"><label for="reason">Sebab ketidakhadiran</label><textarea id="reason" name="reason" maxlength="1000" placeholder="Contoh: Tidak sihat dan mendapatkan rawatan." required></textarea></div><button class="btn btn-primary" type="submit">Simpan dan papar surat</button></form><?php endif; ?></article><article class="card"><h2>Dasar status</h2><p>Notis ialah rekod sebab yang diberikan. Ia tidak menukar kehadiran secara automatik. Guru masih perlu menanda status <strong>Tidak Hadir</strong> di halaman kehadiran manual.</p><p class="muted">Ini mengelakkan status murid berubah tanpa pengesahan guru.</p></article></section><section class="card"><h2>Notis terkini</h2><?php if(!$notices): render_empty('Belum ada notis','Notis yang disimpan akan disenaraikan di sini.'); else: ?><div class="table-wrap"><table class="data-table responsive"><thead><tr><th>Tarikh tidak hadir</th><th>Murid</th><th>Sebab</th><th>Dicipta</th><th></th></tr></thead><tbody><?php foreach($notices as $n): ?><tr><td data-label="Tarikh"><?= e(date('d/m/Y',strtotime($n['absence_date']))) ?></td><td data-label="Murid"><strong><?= e($n['name']) ?></strong><br><small class="muted"><?= e($n['student_id']) ?></small></td><td data-label="Sebab"><?= e($n['reason']) ?></td><td data-label="Dicipta"><?= e(date('d/m/Y h:i A',strtotime($n['created_at']))) ?></td><td data-label="Tindakan"><a class="btn btn-secondary" href="surat.php?id=<?= (int)$n['id'] ?>">Lihat surat</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></div>
<?php render_footer(); ?>
