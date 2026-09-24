<?php

include "db.php";

// ==========================================
// TELEGRAM BOT TOKEN
// ==========================================

$bot_token = "8632174006:AAFnBsI_fTg0TWRShoa7ptKQmytZqk2UVCY";


// ==========================================
// FUNCTION SEND TELEGRAM
// ==========================================

function sendTelegramMessage($chat_id, $message)
{
    global $bot_token;

    $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";

    $data = [
        "chat_id" => $chat_id,
        "text" => $message
    ];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);

    curl_close($ch);

    return $response;
}


// ==========================================
// CHECK BUTTON
// ==========================================

if (isset($_POST['send_absence'])) {

    // Malaysia timezone
    date_default_timezone_set("Asia/Kuala_Lumpur");

    // Today's date
    $today = date("Y-m-d");

    // Current date and time
    $current_time = date("d/m/Y h:i A");


    // ==========================================
    // GET ABSENT STUDENTS TODAY
    // ==========================================

    $sql = "
        SELECT
            student.student_id,
            student.name,
            student.telegram_chat_id
        FROM student
        INNER JOIN attendance
            ON student.student_id = attendance.student_id
        WHERE attendance.date = '$today'
        AND attendance.status = 'Tidak Hadir'
        AND student.telegram_chat_id IS NOT NULL
        AND student.telegram_chat_id != ''
        ORDER BY student.name ASC
    ";

    $result = $conn->query($sql);


    // ==========================================
    // DATABASE ERROR
    // ==========================================

    if (!$result) {

        echo "<script>
                alert('Database Error: " . addslashes($conn->error) . "');
                window.history.back();
              </script>";

        exit;
    }


    // ==========================================
    // NO ABSENT STUDENT
    // ==========================================

    if ($result->num_rows == 0) {

        echo "<script>
                alert('Tiada murid tidak hadir untuk hari ini.');
                window.history.back();
              </script>";

        exit;
    }


    // ==========================================
    // COUNT
    // ==========================================

    $success = 0;
    $failed = 0;


    // ==========================================
    // SEND MESSAGE
    // ==========================================

    while ($row = $result->fetch_assoc()) {

        $student_id = $row['student_id'];
        $student_name = $row['name'];
        $chat_id = $row['telegram_chat_id'];


        // ==========================================
        // TELEGRAM MESSAGE
        // ==========================================

        $message =
            "ABSENCE NOTIFICATION\n\n" .
            "Student ID: " . $student_id . "\n" .
            "Student Name: " . $student_name . "\n" .
            "Date: " . date("d/m/Y", strtotime($today)) . "\n" .
            "Status: Tidak Hadir\n\n" .
            "Notification sent: " . $current_time;


        // ==========================================
        // SEND
        // ==========================================

        $response = sendTelegramMessage(
            $chat_id,
            $message
        );


        // ==========================================
        // CHECK RESPONSE
        // ==========================================

        $telegram_result = json_decode(
            $response,
            true
        );


        if (
            isset($telegram_result['ok']) &&
            $telegram_result['ok'] === true
        ) {

            $success++;

        } else {

            $failed++;
        }
    }


    // ==========================================
    // SHOW RESULT
    // ==========================================

    echo "<script>

        alert(
            'Absence Notification Sent!\\n\\n' +
            'Successfully Sent: $success\\n' +
            'Failed: $failed\\n\\n' +
            'Sent Time: $current_time'
        );

        window.location.href = 'dashboard.php';

    </script>";

    exit;
}

?>