<?php
session_start();

if(!isset($_SESSION['username'])){
    header("Location: login.php");
    exit();
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Teacher Dashboard</title>
</head>

<body>

<h1>
Selamat Datang Teacher
</h1>

<p>
Username: 
<?php echo $_SESSION['username']; ?>
</p>


<a href="attendance.php">
Rekod Kehadiran
</a>

<br>

<a href="report.php">
Laporan Kehadiran
</a>

<br>

<a href="logout.php">
Logout
</a>


</body>
</html>