<?php
session_start();
include("db_connect.php");

if(isset($_POST['login'])){

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $sql = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result = mysqli_query($conn, $sql);

    if(mysqli_num_rows($result) == 1){

        $_SESSION['username'] = $username;
        header("Location: dashboard.php");
        exit();

    }else{
        $error = "Invalid Username or Password!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Login - Face ID Attendance System</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:linear-gradient(135deg, #3949db, #667eea, #8e9cff);
}


/* LOGIN CONTAINER */

.login-container{
    width:420px;
    background:white;
    padding:40px;
    border-radius:25px;
    box-shadow:0 15px 40px rgba(0,0,0,0.20);
    text-align:center;
}


/* CAMERA ICON */

.icon{
    width:85px;
    height:85px;
    margin:0 auto 20px;

    background:#eef0ff;
    border-radius:50%;

    display:flex;
    justify-content:center;
    align-items:center;

    font-size:42px;
}


/* TITLE */

h1{
    color:#303f9f;
    font-size:25px;
    margin-bottom:8px;
}

.subtitle{
    color:#777;
    font-size:14px;
    margin-bottom:30px;
}


/* ERROR */

.error{
    background:#ffe5e5;
    color:#d60000;

    padding:10px;

    border-radius:8px;

    margin-bottom:18px;

    font-size:14px;
}


/* INPUT GROUP */

.input-group{
    position:relative;
    margin-bottom:18px;
}

.input-group span{
    position:absolute;
    left:15px;
    top:12px;

    font-size:20px;
}

input{
    width:100%;

    padding:13px 15px 13px 48px;

    border:1px solid #ddd;

    border-radius:10px;

    font-size:14px;

    outline:none;

    transition:0.3s;
}

input:focus{
    border-color:#3949db;

    box-shadow:0 0 5px rgba(57,73,219,0.25);
}


/* LOGIN BUTTON */

button{
    width:100%;

    padding:14px;

    margin-top:5px;

    background:linear-gradient(135deg,#3949db,#5c6bea);

    color:white;

    border:none;

    border-radius:10px;

    font-size:16px;

    font-weight:bold;

    cursor:pointer;

    transition:0.3s;
}

button:hover{
    background:linear-gradient(135deg,#2836c5,#4858d9);

    transform:translateY(-2px);

    box-shadow:0 5px 15px rgba(57,73,219,0.3);
}


/* FOOTER */

.footer{
    margin-top:25px;

    padding-top:18px;

    border-top:1px solid #eee;

    color:#777;

    font-size:13px;
}

.footer strong{
    color:#3949db;
}


/* SECURITY TEXT */

.security{
    margin-top:12px;

    font-size:12px;

    color:#999;
}

</style>

</head>


<body>


<div class="login-container">


    <!-- CAMERA ICON -->

    <div class="icon">
        🔐
    </div>


    <!-- TITLE -->

    <h1>
        Face ID Attendance
    </h1>

    <div class="subtitle">
        🔐 Login to eAttendance Tabika KEMAS
    </div>


    <?php

    if(isset($error)){
        echo "<div class='error'>⚠️ $error</div>";
    }

    ?>


    <!-- LOGIN FORM -->

    <form method="POST">


        <div class="input-group">

            <span>👤</span>

            <input
                type="text"
                name="username"
                placeholder="Enter Username"
                required>

        </div>


        <div class="input-group">

            <span>🔑</span>

            <input
                type="password"
                name="password"
                placeholder="Enter Password"
                required>

        </div>


        <button type="submit" name="login">

            🔓 Login

        </button>


    </form>


    <!-- FOOTER -->

    <div class="footer">

        📚 <strong>eAttendance Tabika KEMAS</strong>

        <div class="security">
            🛡️ Secure Attendance Management System
        </div>

    </div>


</div>


</body>
</html>