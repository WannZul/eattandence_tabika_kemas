<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth(['admin']);
$studentId=require_student_id($_GET['student_id']??$_POST['student_id']??'');
$stmt=db()->prepare('SELECT student_id,name,class,telegram_chat_id,telegram_linked_at FROM student WHERE student_id=?');$stmt->bind_param('s',$studentId);$stmt->execute();$student=$stmt->get_result()->fetch_assoc();
if(!$student){http_response_code(404);throw new UserFacingException('Murid tidak dijumpai.', 404);}
$link=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_csrf();$username=env_value('TELEGRAM_BOT_USERNAME');if($username===null||preg_match('/^[A-Za-z0-9_]{2,29}bot$/i',$username)!==1){throw new UserFacingException('TELEGRAM_BOT_USERNAME belum dikonfigurasi dengan betul.', 503);}
    $token=rtrim(strtr(base64_encode(random_bytes(24)),'+/','-_'),'=');$hash=hash('sha256',$token);$expires=(new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');
    $update=db()->prepare('UPDATE student SET telegram_link_token_hash=?,telegram_link_expires_at=? WHERE student_id=?');$update->bind_param('sss',$hash,$expires,$studentId);$update->execute();
    $link='https://t.me/'.$username.'?start='.$token;
}
render_header('Pautkan Telegram','Cipta pautan sekali guna untuk ' . $student['name']);
?>
<div class="content-stack"><section class="grid grid-2"><article class="card"><h2><?= e($student['name']) ?></h2><p><strong>ID:</strong> <?= e($student['student_id']) ?><br><strong>Kelas:</strong> <?= e($student['class']) ?></p><p>Status: <?= $student['telegram_chat_id']?'<span class="badge badge-success">Telah dipautkan</span>':'<span class="badge badge-muted">Belum dipautkan</span>' ?></p><form method="post" action="connect_telegram.php"><?= csrf_field() ?><input type="hidden" name="student_id" value="<?= e($studentId) ?>"><button class="btn btn-primary" type="submit">Cipta pautan baharu</button></form></article><article class="card"><h2>Keselamatan pautan</h2><p>Pautan rawak ini tamat dalam 30 minit dan hanya boleh digunakan sekali. ID murid tidak didedahkan dalam arahan Telegram.</p><?php if($link): ?><div class="alert alert-warning"><span>Kongsi pautan hanya dengan penjaga yang disahkan. Mencipta pautan baharu membatalkan pautan lama.</span></div><a class="btn btn-primary" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer">Buka Telegram</a><p class="help-text">Pautan: <code><?= e($link) ?></code></p><?php endif; ?></article></section><a class="btn btn-secondary" href="registration.php">Kembali ke pendaftaran</a></div>
<?php render_footer(); ?>
