<?php

session_start();

include 'db.php';

date_default_timezone_set("Asia/Kuala_Lumpur");


/* ==========================================
   TARIKH YANG DIPILIH
========================================== */

$selected_date = isset($_GET['date'])
    ? $_GET['date']
    : date("Y-m-d");


/* ==========================================
   SIMPAN KEHADIRAN MANUAL
========================================== */

if (isset($_POST['simpan'])) {

    $student_id = mysqli_real_escape_string(
        $conn,
        $_POST['student_id']
    );

    $status = mysqli_real_escape_string(
        $conn,
        $_POST['status']
    );

    $attendance_date = mysqli_real_escape_string(
        $conn,
        $_POST['attendance_date']
    );


    /* ==========================================
       SEMAK STATUS
    ========================================== */

    if ($status != "Hadir" && $status != "Tidak Hadir") {

        die("Sila pilih status kehadiran.");

    }


    /* ==========================================
       AMBIL NAMA MURID
    ========================================== */

    $student_query = mysqli_query($conn, "

        SELECT name

        FROM student

        WHERE student_id = '$student_id'

        LIMIT 1

    ");


    if (!$student_query) {

        die(
            "Student query error: "
            . mysqli_error($conn)
        );

    }


    $student_data = mysqli_fetch_assoc(
        $student_query
    );


    if (!$student_data) {

        die("Student not found.");

    }


    $student_name = mysqli_real_escape_string(
        $conn,
        $student_data['name']
    );


    /* ==========================================
       CHECK REKOD PADA TARIKH DIPILIH
    ========================================== */

    $check = mysqli_query($conn, "

        SELECT id

        FROM attendance

        WHERE student_id = '$student_id'

        AND date = '$attendance_date'

        LIMIT 1

    ");


    if (!$check) {

        die(
            "Attendance check error: "
            . mysqli_error($conn)
        );

    }


    /* ==========================================
       KALAU SUDAH ADA → UPDATE
    ========================================== */

    if (mysqli_num_rows($check) > 0) {

        $update = mysqli_query($conn, "

            UPDATE attendance

            SET
                name = '$student_name',
                status = '$status'

            WHERE student_id = '$student_id'

            AND date = '$attendance_date'

        ");


        if (!$update) {

            die(
                "Update error: "
                . mysqli_error($conn)
            );

        }

    }


    /* ==========================================
       KALAU BELUM ADA → INSERT
    ========================================== */

    else {

        $insert = mysqli_query($conn, "

            INSERT INTO attendance
            (
                student_id,
                name,
                date,
                status
            )

            VALUES
            (
                '$student_id',
                '$student_name',
                '$attendance_date',
                '$status'
            )

        ");


        if (!$insert) {

            die(
                "Insert error: "
                . mysqli_error($conn)
            );

        }

    }


    /* ==========================================
       KEMBALI KE TARIKH YANG DIPILIH
    ========================================== */

    header(
        "Location: manual_kehadiran.php?date="
        . urlencode($attendance_date)
    );

    exit();

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Manual Attendance - eAttendance</title>


<style>

/* ==========================================
   GENERAL
========================================== */

* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;

}


body {

    font-family: Arial, sans-serif;

    background: #f4f6ff;

    color: #333;

}


/* ==========================================
   HEADER
========================================== */

.header {

    background: #3949db;

    color: white;

    padding: 20px 40px;

}


.header h1 {

    font-size: 25px;

}


.header p {

    margin-top: 5px;

    font-size: 14px;

}


/* ==========================================
   MAIN CONTAINER
========================================== */

.container {

    width: 90%;

    max-width: 1200px;

    margin: 30px auto;

}


/* ==========================================
   PAGE TITLE
========================================== */

.page-title {

    background: white;

    padding: 25px;

    border-radius: 10px;

    margin-bottom: 20px;

}


.page-title h2 {

    color: #3949db;

    margin-bottom: 8px;

}


.page-title p {

    color: #666;

}


/* ==========================================
   DATE SECTION
========================================== */

.date-box {

    background: white;

    padding: 20px;

    border-radius: 10px;

    margin-bottom: 20px;

    display: flex;

    align-items: center;

    gap: 15px;

    flex-wrap: wrap;

}


.date-box label {

    font-weight: bold;

    color: #333;

}


.date-box input[type="date"] {

    padding: 10px 12px;

    border: 1px solid #ccc;

    border-radius: 6px;

    font-size: 14px;

    cursor: pointer;

}


.date-box input[type="date"]:focus {

    outline: none;

    border-color: #3949db;

}


/* ==========================================
   DATE BUTTON
========================================== */

.date-btn {

    background: #3949db;

    color: white;

    border: none;

    padding: 10px 18px;

    border-radius: 6px;

    cursor: pointer;

    font-size: 14px;

}


.date-btn:hover {

    background: #2836b8;

}


/* ==========================================
   SELECTED DATE
========================================== */

.selected-date {

    margin-top: 10px;

    width: 100%;

    color: #666;

    font-size: 14px;

}


/* ==========================================
   TABLE BOX
========================================== */

.table-box {

    background: white;

    padding: 20px;

    border-radius: 10px;

    overflow-x: auto;

}


/* ==========================================
   TABLE
========================================== */

table {

    width: 100%;

    border-collapse: collapse;

}


th {

    background: #3949db;

    color: white;

    padding: 14px;

    text-align: center;

}


td {

    padding: 13px;

    border-bottom: 1px solid #ddd;

    text-align: center;

}


tr:hover {

    background: #f8f9ff;

}


/* ==========================================
   SELECT
========================================== */

select {

    padding: 9px 12px;

    border: 1px solid #ccc;

    border-radius: 6px;

    cursor: pointer;

    background: white;

}


select:focus {

    outline: none;

    border-color: #3949db;

}


/* ==========================================
   SAVE BUTTON
========================================== */

.save-btn {

    background: #3949db;

    color: white;

    border: none;

    padding: 9px 18px;

    border-radius: 6px;

    cursor: pointer;

    margin-left: 5px;

}


.save-btn:hover {

    background: #2836b8;

}


/* ==========================================
   STATUS
========================================== */

.status-present {

    color: green;

    font-weight: bold;

}


.status-absent {

    color: red;

    font-weight: bold;

}


.status-none {

    color: #888;

}


/* ==========================================
   BACK BUTTON
========================================== */

.back-btn {

    display: inline-block;

    background: #555;

    color: white;

    text-decoration: none;

    padding: 10px 18px;

    border-radius: 6px;

    margin-top: 20px;

}


.back-btn:hover {

    background: #333;

}


/* ==========================================
   MOBILE
========================================== */

@media(max-width: 700px) {

    .container {

        width: 95%;

    }


    .header {

        padding: 20px;

    }


    .date-box {

        align-items: flex-start;

        flex-direction: column;

    }


    th,
    td {

        font-size: 13px;

        padding: 10px;

    }

}

</style>

</head>


<body>


<!-- ==========================================
     HEADER
========================================== -->

<div class="header">

    <h1>
        eAttendance Tabika KEMAS
    </h1>

    <p>
        Manual Attendance
    </p>

</div>



<!-- ==========================================
     MAIN
========================================== -->

<div class="container">


    <!-- PAGE TITLE -->

    <div class="page-title">

        <h2>
            Manual Attendance
        </h2>

    </div>



    <!-- ==========================================
         CALENDAR
    ========================================== -->

    <div class="date-box">

        <label for="attendance_date">

            Pilih Tarikh Kehadiran:

        </label>


        <input
            type="date"
            id="attendance_date"
            value="<?php echo htmlspecialchars($selected_date); ?>"
        >

        <div class="selected-date">

            Tarikh dipilih:

            <strong>

                <?php

                echo date(
                    "d/m/Y",
                    strtotime($selected_date)
                );

                ?>

            </strong>

        </div>

    </div>



    <!-- ==========================================
         TABLE
    ========================================== -->

    <div class="table-box">

        <table>

            <tr>

                <th>
                    Bil
                </th>

                <th>
                    ID Murid
                </th>

                <th>
                    Nama Murid
                </th>

                <th>
                    Status Sekarang
                </th>

                <th>
                    Tanda Kehadiran
                </th>

            </tr>


            <?php

            $bil = 1;


            /* ==========================================
               AMBIL SEMUA MURID
            ========================================== */

            $students = mysqli_query($conn, "

                SELECT
                    student_id,
                    name

                FROM student

                ORDER BY name ASC

            ");


            if (!$students) {

                die(
                    "Student query error: "
                    . mysqli_error($conn)
                );

            }


            if (mysqli_num_rows($students) > 0) {


                while (
                    $student =
                    mysqli_fetch_assoc($students)
                ) {


                    $student_id =
                        $student['student_id'];


                    $student_name =
                        $student['name'];


                    /* ==========================================
                       CHECK ATTENDANCE TARIKH DIPILIH
                    ========================================== */

                    $attendance = mysqli_query($conn, "

                        SELECT status

                        FROM attendance

                        WHERE student_id =
                            '$student_id'

                        AND date =
                            '$selected_date'

                        LIMIT 1

                    ");


                    if (!$attendance) {

                        die(
                            "Attendance query error: "
                            . mysqli_error($conn)
                        );

                    }


                    $current_status = "";


                    if (
                        mysqli_num_rows($attendance) > 0
                    ) {

                        $attendance_data =
                            mysqli_fetch_assoc(
                                $attendance
                            );


                        $current_status =
                            $attendance_data['status'];

                    }

            ?>


            <tr>


                <!-- BIL -->

                <td>

                    <?php

                    echo $bil++;

                    ?>

                </td>



                <!-- ID -->

                <td>

                    <?php

                    echo htmlspecialchars(
                        $student_id
                    );

                    ?>

                </td>



                <!-- NAME -->

                <td>

                    <?php

                    echo htmlspecialchars(
                        $student_name
                    );

                    ?>

                </td>



                <!-- CURRENT STATUS -->

                <td>


                    <?php

                    if (
                        $current_status == "Hadir"
                    ) {

                        echo "

                        <span class='status-present'>

                            HADIR

                        </span>

                        ";

                    }

                    elseif (
                        $current_status == "Tidak Hadir"
                    ) {

                        echo "

                        <span class='status-absent'>

                            TIDAK HADIR

                        </span>

                        ";

                    }

                    else {

                        echo "

                        <span class='status-none'>

                            BELUM DITANDA

                        </span>

                        ";

                    }

                    ?>


                </td>



                <!-- MARK ATTENDANCE -->

                <td>


                    <form
                        method="POST"
                        action="manual_kehadiran.php"
                    >


                        <!-- STUDENT ID -->

                        <input
                            type="hidden"
                            name="student_id"
                            value="<?php

                            echo htmlspecialchars(
                                $student_id
                            );

                            ?>"
                        >


                        <!-- SELECTED DATE -->

                        <input
                            type="hidden"
                            name="attendance_date"
                            value="<?php

                            echo htmlspecialchars(
                                $selected_date
                            );

                            ?>"
                        >


                        <!-- STATUS -->

                        <select
                            name="status"
                            required
                        >


                            <option value="">

                                Pilih Status

                            </option>


                            <option
                                value="Hadir"

                                <?php

                                if (
                                    $current_status ==
                                    "Hadir"
                                ) {

                                    echo "selected";

                                }

                                ?>
                            >

                                Hadir

                            </option>


                            <option
                                value="Tidak Hadir"

                                <?php

                                if (
                                    $current_status ==
                                    "Tidak Hadir"
                                ) {

                                    echo "selected";

                                }

                                ?>
                            >

                                Tidak Hadir

                            </option>


                        </select>


                        <!-- SAVE -->

                        <button
                            type="submit"
                            name="simpan"
                            class="save-btn"
                        >

                            Simpan

                        </button>


                    </form>


                </td>


            </tr>


            <?php

                }

            }

            else {

            ?>


            <tr>

                <td colspan="5">

                    Tiada data murid.

                </td>

            </tr>


            <?php

            }

            ?>


        </table>

    </div>



    <!-- ==========================================
         BACK
    ========================================== -->

    <a
        href="dashboard.php"
        class="back-btn"
    >

        ← Back to Dashboard

    </a>


</div>



<!-- ==========================================
     JAVASCRIPT CALENDAR
========================================== -->

<script>

function changeDate() {

    var date =
        document.getElementById(
            "attendance_date"
        ).value;


    if (date == "") {

        alert("Sila pilih tarikh.");

        return;

    }


    window.location.href =
        "manual_kehadiran.php?date="
        + date;

}

</script>


</body>

</html>