<?php

session_start();


// ==========================================
// DATABASE CONNECTION
// ==========================================

$host = "localhost";
$user = "root";
$password = "";
$database = "face_attendance";

$conn = new mysqli(
    $host,
    $user,
    $password,
    $database
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");


// ==========================================
// TELEGRAM
// ==========================================

require_once "telegram.php";


// ==========================================
// DATE TODAY
// ==========================================

$today = date("Y-m-d");


// ==========================================
// FIND ABSENT STUDENTS
// ==========================================

$sql = "
SELECT 
    s.student_id,
    s.name,
    s.telegram_chat_id

FROM student s

LEFT JOIN attendance a
ON s.student_id = a.student_id
AND a.date = '$today'

WHERE a.student_id IS NULL
";

$result = $conn->query($sql);


// ==========================================
// CHECK RESULT
// ==========================================

if (!$result) {
    die("Error: " . $conn->error);
}


$total_sent = 0;
$total_absent = 0;


// ==========================================
// SEND NOTIFICATION
// ==========================================

while ($row = $result->fetch_assoc()) {

    $student_id = $row['student_id'];
    $student_name = $row['name'];
    $chat_id = $row['telegram_chat_id'];

    $total_absent++;


    // --------------------------------------
    // CHECK TELEGRAM CHAT ID
    // --------------------------------------

    if (empty($chat_id)) {
        continue;
    }


    // --------------------------------------
    // FORMAT DATE
    // --------------------------------------

    $display_date = date("d/m/Y", strtotime($today));


    // --------------------------------------
    // TELEGRAM MESSAGE
    // --------------------------------------

    $message = "eAttendance Tabika KEMAS\n\n";

    $message .= "Assalamualaikum.\n\n";

    $message .= "Dimaklumkan bahawa anak anda:\n\n";

    $message .= "Nama: " . $student_name . "\n";

    $message .= "ID Murid: " . $student_id . "\n";

    $message .= "Tarikh: " . $display_date . "\n";

    $message .= "Status: TIDAK HADIR\n\n";

    $message .= "Sila kemukakan sebab ketidakhadiran melalui sistem eAttendance.\n\n";

    $message .= "Terima kasih.";


    // --------------------------------------
    // SEND TELEGRAM
    // --------------------------------------

    $response = sendTelegramMessage(
        $chat_id,
        $message
    );


    if ($response) {
        $total_sent++;
    }
}


// ==========================================
// CLOSE DATABASE
// ==========================================

$conn->close();


// ==========================================
// RESULT
// ==========================================

echo "<script>";

echo "alert('Notification selesai dihantar!');";

echo "window.location.href='dashboard.php';";

echo "</script>";

?>