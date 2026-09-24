<?php

$conn = mysqli_connect("localhost","root","","face_attendance");

if(!$conn){
    die("Connection Failed : ".mysqli_connect_error());
}

$date = date("Y-m-d");


$sql = "
SELECT *
FROM attendance
WHERE date = '$date'
ORDER BY id DESC
";


$result = mysqli_query($conn,$sql);


if(!$result){
    die("SQL Error : ".mysqli_error($conn));
}

?>


<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Status Kehadiran</title>


<style>

body{
    font-family: Arial, sans-serif;
    background:#eef0ff;
}


.container{

    width:90%;
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


.hadir{

    color:green;
    font-weight:bold;

}


.tidak{

    color:red;
    font-weight:bold;

}


.back{

    display:inline-block;
    margin-top:20px;
    background:#3949db;
    color:white;
    padding:10px 20px;
    text-decoration:none;
    border-radius:8px;

}


</style>

</head>


<body>


<div class="container">


<h2>Status Kehadiran Hari Ini</h2>

<h4>
Tarikh : <?php echo date("d-m-Y"); ?>
</h4>


<table>


<tr>

<th>ID</th>

<th>Nama</th>

<th>Status</th>

<th>Masa</th>

</tr>


<?php


if(mysqli_num_rows($result)>0){


while($row=mysqli_fetch_assoc($result)){


echo "

<tr>

<td>".$row['id']."</td>

<td>".$row['name']."</td>

<td>";



if($row['status']=="Present"){

echo "<span class='present'>Present</span>";

}

else{

echo "<span class='Absent'>Absent</span>";

}



echo "

</td>

<td>".$row['time']."</td>

</tr>


";


}


}

else{


echo "

<tr>

<td colspan='4'>
Tiada rekod kehadiran hari ini
</td>

</tr>

";


}


?>


</table>


<a class="back" href="dashboard.php">
Kembali
</a>


</div>


</body>
</html>