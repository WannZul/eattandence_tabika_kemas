<?php

$conn = mysqli_connect("localhost","root","","face_attendance");

if(!$conn){
    die("Connection Failed : ".mysqli_connect_error());
}

?>