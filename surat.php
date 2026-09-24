<?php

include "db.php";


// =====================================
// CHECK DATA
// =====================================

if (
    !isset($_POST['student_id']) ||
    !isset($_POST['absence_date']) ||
    !isset($_POST['reason'])
) {

    die("Maklumat tidak lengkap.");

}


// =====================================
// GET DATA
// =====================================

$student_id = $_POST['student_id'];

$absence_date = $_POST['absence_date'];

$reason = $_POST['reason'];


// =====================================
// GET STUDENT
// =====================================

$sql = "
SELECT student_id, name
FROM student
WHERE student_id = ?
";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "s",
    $student_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows == 0) {

    die("Murid tidak dijumpai.");

}


$student = $result->fetch_assoc();

$studentName = $student['name'];


// =====================================
// DATE
// =====================================

$date = new DateTime($absence_date);

$formattedDate = $date->format("d/m/Y");

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<title>Surat Ketidakhadiran</title>


<style>

body {

    font-family: Arial, sans-serif;

    background: #eee;

    margin: 0;

    padding: 30px;

}


.surat {

    width: 210mm;

    min-height: 297mm;

    background: white;

    margin: auto;

    padding: 25mm;

    box-sizing: border-box;

}


.header {

    text-align: center;

    margin-bottom: 30px;

}


.header h2 {

    margin-bottom: 5px;

}


.header p {

    margin: 3px;

}


.tarikh {

    text-align: right;

    margin-bottom: 30px;

}


.title {

    text-align: center;

    font-weight: bold;

    text-decoration: underline;

    margin: 25px 0;

}


.content {

    line-height: 1.8;

    text-align: justify;

}


.info {

    margin: 20px 0;

}


.signature {

    margin-top: 60px;

}


.button-area {

    text-align: center;

    margin: 20px;

}


button {

    padding: 12px 25px;

    border: none;

    background: #2c7be5;

    color: white;

    border-radius: 5px;

    cursor: pointer;

    margin: 5px;

}


@media print {

    body {

        background: white;

        padding: 0;

    }


    .button-area {

        display: none;

    }


    .surat {

        width: 210mm;

        min-height: 297mm;

        margin: 0;

        padding: 25mm;

    }

}

</style>

</head>


<body>


<div class="button-area">

<button onclick="window.print()">

Print / Save PDF

</button>


<button onclick="history.back()">

Kembali

</button>

</div>


<div class="surat">


<div class="header">

<h2>TABIKA KEMAS</h2>

<p>Sistem eAttendance</p>

</div>


<div class="tarikh">

Tarikh: <?php echo $formattedDate; ?>

</div>


<div class="title">

SURAT MAKLUMAN KETIDAKHADIRAN MURID

</div>


<p>

Kepada,

<br>

Guru Tabika KEMAS

</p>


<p>

Tuan/Puan,

</p>


<div class="title">

MAKLUMAN KETIDAKHADIRAN MURID

</div>


<div class="info">

<table width="100%" cellpadding="8">

<tr>

<td width="35%">

<strong>Nama Murid</strong>

</td>

<td>

:

<?php echo htmlspecialchars($studentName); ?>

</td>

</tr>


<tr>

<td>

<strong>ID Murid</strong>

</td>

<td>

:

<?php echo htmlspecialchars($student_id); ?>

</td>

</tr>


<tr>

<td>

<strong>Tarikh Tidak Hadir</strong>

</td>

<td>

:

<?php echo $formattedDate; ?>

</td>

</tr>

</table>

</div>


<div class="content">


<p>

Dengan segala hormatnya perkara di atas adalah dirujuk.

</p>


<p>

Dimaklumkan bahawa anak/jagaan saya,

<strong>

<?php echo htmlspecialchars($studentName); ?>

</strong>,

tidak dapat hadir ke Tabika KEMAS pada

<strong>

<?php echo $formattedDate; ?>

</strong>

atas sebab berikut:

</p>


<p>

<strong>

<?php echo nl2br(htmlspecialchars($reason)); ?>

</strong>

</p>


<p>

Sehubungan dengan itu, saya memohon agar pihak Tabika KEMAS

mengambil maklum berkenaan ketidakhadiran anak/jagaan saya

pada tarikh tersebut.

</p>


<p>

Kerjasama dan perhatian daripada pihak tuan/puan amat dihargai.

</p>


<p>

Sekian, terima kasih.

</p>


</div>


<div class="signature">

Yang benar,

<br><br><br>

______________________________

<br>

Ibu/Bapa/Penjaga

</div>


</div>


</body>

</html>