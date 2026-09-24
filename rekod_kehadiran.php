<?php

$conn = mysqli_connect("localhost", "root", "", "face_attendance");

if (!$conn) {
    die("Connection Failed : " . mysqli_connect_error());
}

$sql = "SELECT * FROM attendance ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

$total = mysqli_num_rows($result);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Rekod Kehadiran Murid</title>
<link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;500&display=swap" rel="stylesheet">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Lato', sans-serif;
}
body{

    background:#eef2ff;

}

.container{

    width:90%;
    max-width:1050px;
    margin:50px auto;
    background:white;
    padding:30px;
    border-radius:15px;
    box-shadow:0 8px 20px rgba(0,0,0,.1);

}

.title{

    text-align:center;
    color:#3f51d9;
    margin-bottom:8px;
    font-size:26px;

}

.date{

    text-align:center;
    color:#777;
    margin-bottom:20px;

}

.total{

    width:180px;
    margin:0 auto 30px;
    text-align:center;
    background:#3f51d9;
    color:white;
    padding:12px;
    border-radius:10px;
    font-weight:bold;

}

table{

    width:100%;
    border-collapse:collapse;

}

th{

    background:#3f51d9;
    color:white;
    padding:15px;

}

td{

    padding:15px;
    text-align:center;
    border-bottom:1px solid #ddd;

}

tr:nth-child(even){

    background:#f8f9ff;

}

tr:hover{

    background:#edf1ff;

}

.status{

    background:#28a745;
    color:white;
    padding:7px 15px;
    border-radius:20px;
    font-size:14px;
    font-weight:bold;

}

.back{

    display:inline-block;
    margin-top:30px;
    background:#3f51d9;
    color:white;
    text-decoration:none;
    padding:12px 25px;
    border-radius:8px;

}

.back:hover{

    background:#2d3fc5;

}

.empty{

    padding:30px;
    text-align:center;
    color:#888;

}

</style>

</head>

<body>
    <div class="container">
        <h2 class="title">
            <i class="fa-solid fa-clipboard-check"></i>
            Rekod Kehadiran Murid
        </h2>
        <p class="date"><?php echo date("d F Y"); ?></p>
        <div class="total">
            Jumlah Rekod : <?php echo $total; ?>
        </div>
        <table>
            <tr>
                <th>No</th>
                <th>ID Murid</th>
                <th>Nama Murid</th>
                <th>Tarikh</th>
                <th>Masa</th>
                <th>Status</th>
            </tr>
            <?php
            if($total>0){
                $no=1;
                while($row=mysqli_fetch_assoc($result)){
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $row['student_id']; ?></td>
                        <td><?php echo $row['name']; ?></td>
                        <td><?php echo date("d-m-Y",strtotime($row['date'])); ?></td>
                        <td><?php echo date("h:i:s A",strtotime($row['time'])); ?></td>
                        <td>
                            <span class="status">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php
                    }
                    }
                    else{
                        ?>
                        <tr>
                            <td colspan="6" class="empty">
                                Tiada rekod kehadiran dijumpai.
                            </td>
                        </tr>
                        <?php
                        }
                        ?>
                        </table>
                        <a href="dashboard.php" class="back">
                            <i class="fa-solid fa-arrow-left"></i>
                            Kembali ke Dashboard
                        </a>
                    </div>
                </body>
                </html>
                <?php
                mysqli_close($conn);
                ?>