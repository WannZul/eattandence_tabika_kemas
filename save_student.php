<?php

header("Content-Type: application/json");


// ================= DATABASE =================

$servername = "localhost";
$username = "root";
$password = "";
$database = "face_attendance";


$conn = new mysqli(
    $servername,
    $username,
    $password,
    $database
);


// Check database connection

if ($conn->connect_error) {

    echo json_encode([
        "success" => false,
        "message" => "Database connection failed: " . $conn->connect_error
    ]);

    exit;
}


// Set character

$conn->set_charset("utf8");


// ================= GET JSON DATA =================

$json = file_get_contents("php://input");

$data = json_decode($json, true);


// Check JSON

if ($data === null) {

    echo json_encode([
        "success" => false,
        "message" => "Data tidak diterima oleh server."
    ]);

    exit;
}


// ================= STUDENT INFORMATION =================

$student_id = trim($data["student_id"] ?? "");

$student_name = trim($data["student_name"] ?? "");

$student_class = trim($data["student_class"] ?? "");

$images = $data["images"] ?? [];


// ================= VALIDATION =================

if ($student_id == "") {

    echo json_encode([
        "success" => false,
        "message" => "Student ID tidak boleh kosong."
    ]);

    exit;
}


if ($student_name == "") {

    echo json_encode([
        "success" => false,
        "message" => "Student Name tidak boleh kosong."
    ]);

    exit;
}


if ($student_class == "") {

    echo json_encode([
        "success" => false,
        "message" => "Class tidak boleh kosong."
    ]);

    exit;
}


if (!is_array($images)) {

    echo json_encode([
        "success" => false,
        "message" => "Gambar tidak sah."
    ]);

    exit;
}


if (count($images) != 6) {

    echo json_encode([
        "success" => false,
        "message" => "Sila ambil 6 gambar terlebih dahulu."
    ]);

    exit;
}


// ================= CHECK STUDENT ID =================

$check = $conn->prepare(
    "SELECT student_id FROM student WHERE student_id = ?"
);


if (!$check) {

    echo json_encode([
        "success" => false,
        "message" => "SQL Error: " . $conn->error
    ]);

    exit;
}


$check->bind_param(
    "s",
    $student_id
);


$check->execute();


$result = $check->get_result();


if ($result->num_rows > 0) {

    echo json_encode([
        "success" => false,
        "message" => "Student ID $student_id sudah wujud. Sila gunakan ID lain."
    ]);

    $check->close();

    $conn->close();

    exit;
}


$check->close();


// ================= INSERT STUDENT =================

$stmt = $conn->prepare(
    "INSERT INTO student (student_id, name, class)
     VALUES (?, ?, ?)"
);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "SQL Error semasa menyediakan data: " . $conn->error
    ]);

    exit;
}


$stmt->bind_param(
    "sss",
    $student_id,
    $student_name,
    $student_class
);


if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" => "Gagal menyimpan murid: " . $stmt->error
    ]);

    $stmt->close();

    $conn->close();

    exit;
}


$stmt->close();


// ================= CREATE DATASET FOLDER =================

$datasetFolder = "dataset";


if (!is_dir($datasetFolder)) {

    if (!mkdir($datasetFolder, 0777, true)) {

        echo json_encode([
            "success" => false,
            "message" => "Folder dataset tidak dapat dibuat."
        ]);

        exit;
    }
}


// ================= CLEAN NAME =================

$cleanName = preg_replace(
    "/[^A-Za-z0-9_-]/",
    "_",
    $student_name
);


// ================= STUDENT FOLDER =================

$studentFolder =
    $datasetFolder .
    "/" .
    $student_id .
    "_" .
    $cleanName;


if (!is_dir($studentFolder)) {

    if (!mkdir($studentFolder, 0777, true)) {

        echo json_encode([
            "success" => false,
            "message" => "Folder murid tidak dapat dibuat."
        ]);

        exit;
    }
}


// ================= SAVE IMAGES =================

$imageNumber = 1;


foreach ($images as $image) {


    // Check base64

    if (strpos($image, "base64,") === false) {

        echo json_encode([
            "success" => false,
            "message" => "Format gambar $imageNumber tidak sah."
        ]);

        exit;
    }


    // Get base64 data

    $imageParts = explode(
        "base64,",
        $image,
        2
    );


    $imageData = base64_decode(
        $imageParts[1]
    );


    if ($imageData === false) {

        echo json_encode([
            "success" => false,
            "message" => "Gagal memproses gambar $imageNumber."
        ]);

        exit;
    }


    // File name

    $fileName =
        $studentFolder .
        "/" .
        $imageNumber .
        ".jpg";


    // Save image

    $saved = file_put_contents(
        $fileName,
        $imageData
    );


    if ($saved === false) {

        echo json_encode([
            "success" => false,
            "message" => "Gagal menyimpan gambar $imageNumber."
        ]);

        exit;
    }


    $imageNumber++;
}


// ================= CLOSE DATABASE =================

$conn->close();


// ================= SUCCESS =================

echo json_encode([

    "success" => true,

    "message" =>
        "Murid berjaya didaftarkan dan 6 gambar berjaya disimpan."

]);

?>