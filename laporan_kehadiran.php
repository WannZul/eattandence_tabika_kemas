<?php

$conn = mysqli_connect("localhost","root","","face_attendance");

if(!$conn){
    die("Connection Failed");
}


$month = date("m");
$year = date("Y");


$sql="
SELECT 
student.name,
COUNT(attendance.id) AS jumlah_hadir

FROM student

LEFT JOIN attendance

ON student.student_id = attendance.student_id

AND MONTH(attendance.date)='$month'

AND YEAR(attendance.date)='$year'

GROUP BY student.student_id
";


$result=mysqli_query($conn,$sql);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kehadiran</title>


<style>

body{
font-family:Arial;
background:#eef0ff;
}


.box{

width:80%;
margin:50px auto;
background:white;
padding:30px;
border-radius:15px;

}


h2{

text-align:center;
color:#3949db;

}


table{

width:100%;
border-collapse:collapse;

}


th{

background:#3949db;
color:white;
padding:12px;

}


td{

padding:12px;
text-align:center;
border-bottom:1px solid #ddd;

}



button{

background:#3949db;
color:white;
border:none;
padding:10px 20px;
border-radius:8px;

}



</style>

</head>
<body>
    <div class="box">
        <h2>Laporan Kehadiran Bulanan</h2>

        <p>Bulan :<?php echo date("F Y"); ?></p>
        <table>
            <tr>
                <th>Nama Murid</th>
                <th>Jumlah Hadir</th>
            </tr>
            <?php
            while($row=mysqli_fetch_assoc($result)){
                echo "
                <tr>
                <td>".$row['name']."</td>
                <td>".$row['jumlah_hadir']." hari</td>
                </tr>
                ";
                }
            ?>
            </table>
            <br>
            <a href="dashboard.php">
                <button>Kembali</button>
            </a>
        </div>   
</body>
</html>