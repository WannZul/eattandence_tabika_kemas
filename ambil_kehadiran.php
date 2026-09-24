<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ambil Kehadiran</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, Helvetica, sans-serif;
}

body{
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:linear-gradient(135deg,#dfe9ff,#f5f7ff);
}

.box{
    width:520px;
    background:#fff;
    border-radius:20px;
    padding:45px;
    text-align:center;
    box-shadow:0 15px 35px rgba(0,0,0,0.15);
}

.icon{
    width:110px;
    height:110px;
    background:#edf1ff;
    border-radius:50%;
    margin:auto;
    display:flex;
    justify-content:center;
    align-items:center;
    color:#3f51d9;
    font-size:55px;
}

h2{
    margin-top:25px;
    color:#3949db;
    font-size:30px;
}

.subtitle{
    margin-top:8px;
    color:#666;
    font-size:18px;
}

.line{
    width:90px;
    height:4px;
    background:#3949db;
    margin:20px auto;
    border-radius:50px;
}

.info{
    color:#777;
    line-height:28px;
    font-size:16px;
    margin-bottom:35px;
}

button{
    width:100%;
    padding:16px;
    border:none;
    border-radius:12px;
    background:#3949db;
    color:white;
    font-size:18px;
    font-weight:bold;
    cursor:pointer;
    transition:0.3s;
}

button i{
    margin-right:10px;
}

button:hover{
    background:#2738c8;
    transform:translateY(-3px);
    box-shadow:0 8px 18px rgba(57,73,219,.4);
}

.footer{
    margin-top:25px;
    color:#888;
    font-size:14px;
}

</style>

</head>
<body>

<div class="box">

    <div class="icon">
        <i class="fa-solid fa-camera"></i>
    </div>

    <h2>eAttendance</h2>

    <p class="subtitle">
        Tabika KEMAS
    </p>

    <div class="line"></div>

    <p class="info">
        Klik butang di bawah untuk memulakan proses
        <strong>Face Recognition</strong> bagi merekod
        kehadiran murid.
    </p>

    <a href="scan.php">
        <button>
            <i class="fa-solid fa-face-smile"></i>
            Scan Face
        </button>
    </a>

    <div class="footer">
        Face Recognition Attendance System
    </div>

</div>

</body>
</html>