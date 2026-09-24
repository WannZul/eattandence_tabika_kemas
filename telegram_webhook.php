<?php

include "db.php";

// ==========================================
// TELEGRAM BOT TOKEN
// ==========================================

$bot_token = "8632174006:AAFnBsI_fTg0TWRShoa7ptKQmytZqk2UVCY";


// ==========================================
// GET TELEGRAM UPDATES
// ==========================================

$url = "https://api.telegram.org/bot" . $bot_token . "/getUpdates";

$response = file_get_contents($url);

$data = json_decode($response, true);


// ==========================================
// CHECK TELEGRAM
// ==========================================

if (!$data || !$data['ok']) {

    echo "Telegram connection failed.";
    exit;
}


// ==========================================
// CHECK UPDATE
// ==========================================

if (count($data['result']) == 0) {

    echo "Tiada Telegram update.";
    exit;
}


// ==========================================
// GET LATEST UPDATE
// ==========================================

$latest = end($data['result']);


// ==========================================
// CHECK MESSAGE
// ==========================================

if (!isset($latest['message'])) {

    echo "Tiada message dijumpai.";
    exit;
}


$message = $latest['message'];


// ==========================================
// GET CHAT ID
// ==========================================

$chat_id = $message['chat']['id'];


// ==========================================
// GET TEXT
// ==========================================

$text = "";

if (isset($message['text'])) {

    $text = $message['text'];
}


// ==========================================
// CHECK /START
// ==========================================

if (strpos($text, "/start") !== 0) {

    echo "Bukan command START.";
    exit;
}


// ==========================================
// GET STUDENT ID
// ==========================================

// Contoh:
// /start M001

$parts = explode(" ", $text);


if (!isset($parts[1])) {

    echo "Student ID tidak dijumpai.";
    exit;
}


$student_id = trim($parts[1]);


// ==========================================
// CHECK STUDENT
// ==========================================

$student_id_safe =
    $conn->real_escape_string($student_id);


$sql = "
    SELECT student_id, name
    FROM student
    WHERE student_id = '$student_id_safe'
";


$result = $conn->query($sql);


if (!$result || $result->num_rows == 0) {

    echo "Student ID tidak wujud.";

    exit;
}


$student = $result->fetch_assoc();

$student_name = $student['name'];


// ==========================================
// SAVE CHAT ID
// ==========================================

$chat_id_safe =
    $conn->real_escape_string($chat_id);


$update_sql = "
    UPDATE student
    SET telegram_chat_id = '$chat_id_safe'
    WHERE student_id = '$student_id_safe'
";


if ($conn->query($update_sql)) {

    echo "<h2>SUCCESS!</h2>";

    echo "Student: " .
        htmlspecialchars($student_name) .
        "<br><br>";

    echo "Student ID: " .
        htmlspecialchars($student_id) .
        "<br><br>";

    echo "Telegram Chat ID saved successfully.";

} else {

    echo "Database update failed.";

}

?>