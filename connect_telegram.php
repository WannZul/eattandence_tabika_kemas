<?php

include "db.php";

// ==========================================
// TELEGRAM BOT USERNAME
// ==========================================

$bot_username = "eAttendanceKEMASBot";


// ==========================================
// CHECK STUDENT ID
// ==========================================

if (!isset($_GET['student_id'])) {

    echo "Student ID tidak dijumpai.";
    exit;
}

$student_id = $_GET['student_id'];


// ==========================================
// GET STUDENT
// ==========================================

$sql = "SELECT student_id, name 
        FROM student 
        WHERE student_id = '$student_id'";

$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {

    echo "Murid tidak dijumpai.";
    exit;
}

$row = $result->fetch_assoc();

$student_name = $row['name'];


// ==========================================
// CREATE TELEGRAM LINK
// ==========================================

$telegram_link =
    "https://t.me/" .
    $bot_username .
    "?start=" .
    urlencode($student_id);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Connect Telegram</title>

<style>

body {

    font-family: Arial, sans-serif;

    background: #f4f6f9;

    padding: 40px;

}

.container {

    width: 500px;

    margin: auto;

    background: white;

    padding: 30px;

    border-radius: 10px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.1);

    text-align: center;

}

h1 {

    margin-bottom: 15px;

}

.student {

    margin-bottom: 25px;

    color: #555;

}

.telegram-btn {

    display: inline-block;

    padding: 13px 25px;

    background: #2c7be5;

    color: white;

    text-decoration: none;

    border-radius: 6px;

    font-size: 16px;

}

.telegram-btn:hover {

    background: #1a68d1;

}

.info {

    margin-top: 25px;

    color: #777;

    line-height: 1.6;

}

</style>

</head>

<body>


<div class="container">

    <h1>Connect Telegram</h1>

    <p class="student">

        Student:

        <strong>
            <?php echo htmlspecialchars($student_name); ?>
        </strong>

    </p>


    <a
        href="<?php echo htmlspecialchars($telegram_link); ?>"
        class="telegram-btn"
        target="_blank"
    >

        Connect Telegram

    </a>


    <p class="info">

        1. Click the button above.<br>

        2. Telegram will open.<br>

        3. Press <strong>START</strong>.<br>

        4. Your Telegram account will be connected automatically.

    </p>

</div>


</body>

</html>