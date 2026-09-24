<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth();
render_header('Notifikasi Ketidakhadiran','Endpoint lama telah dinyahaktifkan dengan selamat');
?>
<div class="content-stack"><section class="card"><div class="alert alert-info"><span>Penghantaran kini dibuat melalui Dashboard menggunakan POST, token CSRF dan rekod deduplikasi. Hanya status “Tidak Hadir” digunakan.</span></div><a class="btn btn-primary" href="dashboard.php">Pergi ke Dashboard</a></section></div>
<?php render_footer(); ?>
