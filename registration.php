<?php
session_start();

/*
if(!isset($_SESSION['username'])){
    header("Location: login.php");
    exit();
}
*/
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Student Registration - eAttendance</title>

<style>

/* ================= GENERAL ================= */

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    background:#f2f5ff;
    min-height:100vh;
}


/* ================= HEADER ================= */

.header{
    height:90px;
    width:100%;

    background:#3949db;
    color:white;

    display:flex;
    justify-content:space-between;
    align-items:center;

    padding:0 30px;

    box-shadow:0 2px 8px rgba(0,0,0,0.2);

    position:relative;
    z-index:1000;
}

.header-left{
    display:flex;
    align-items:center;
}


/* ================= LOGO ================= */

.logo-kemas{
    width:85px;
    height:85px;
    object-fit:contain;
    margin-right:20px;
}


/* ================= TITLE ================= */

.title h2{
    font-size:22px;
    margin:0;
}

.title p{
    font-size:16px;
    margin-top:5px;
    color:white;
}


/* ================= MENU BUTTON ================= */

.menu-btn{
    width:45px;
    height:45px;

    background:transparent;
    border:none;

    color:white;

    font-size:28px;

    cursor:pointer;

    border-radius:8px;

    transition:0.3s;
}

.menu-btn:hover{
    background:rgba(255,255,255,0.15);
}


/* ================= SIDEBAR ================= */

.sidebar{
    position:fixed;

    top:90px;
    right:0;

    width:260px;
    height:calc(100vh - 90px);

    background:white;

    box-shadow:-4px 0 15px rgba(0,0,0,0.15);

    z-index:999;

    transition:0.3s;

    overflow:hidden;
}

.sidebar.closed{
    right:-260px;
}


/* ================= SIDEBAR TITLE ================= */

.sidebar-title{
    text-align:center;

    padding:25px 10px;

    font-size:20px;

    font-weight:bold;

    color:#3949db;

    border-bottom:1px solid #ddd;
}


/* ================= MENU ================= */

.sidebar ul{
    list-style:none;
}

.sidebar ul li{
    border-bottom:1px solid #eee;
}

.sidebar ul li a{
    display:flex;
    align-items:center;

    padding:18px 20px;

    color:#333;

    text-decoration:none;

    font-size:16px;

    transition:0.3s;
}

.sidebar ul li a:hover{
    background:#3949db;
    color:white;
}


/* ================= MENU ICON ================= */

.menu-icon{
    width:35px;

    font-size:20px;

    margin-right:12px;

    text-align:center;
}


/* ================= OVERLAY ================= */

.overlay{
    position:fixed;

    top:90px;
    left:0;

    width:100%;
    height:calc(100vh - 90px);

    background:rgba(0,0,0,0.25);

    z-index:998;

    display:none;
}

.overlay.active{
    display:block;
}


/* ================= CONTENT ================= */

.content{
    padding:35px;

    width:100%;

    min-height:calc(100vh - 90px);
}


/* ================= REGISTRATION CARD ================= */

.registration-card{

    max-width:800px;

    margin:0 auto;

    background:white;

    padding:35px;

    border-radius:15px;

    box-shadow:0 3px 10px rgba(0,0,0,0.08);
}


/* ================= TITLE ================= */

.registration-card h2{

    text-align:center;

    color:#3949db;

    font-size:26px;

    margin-bottom:8px;
}

.registration-card .description{

    text-align:center;

    color:#777;

    font-size:14px;

    margin-bottom:30px;
}


/* ================= FORM ================= */

.form-group{

    margin-bottom:18px;
}

.form-group label{

    display:block;

    font-weight:bold;

    color:#333;

    margin-bottom:7px;

    font-size:14px;
}

.form-group input{

    width:100%;

    padding:13px;

    border:1px solid #ccc;

    border-radius:8px;

    font-size:15px;

    outline:none;
}

.form-group input:focus{

    border-color:#3949db;

    box-shadow:0 0 0 2px rgba(57,73,219,0.1);
}


/* ================= CAMERA ================= */

.camera-title{

    text-align:center;

    color:#3949db;

    font-size:20px;

    margin-top:25px;

    margin-bottom:15px;
}

.camera-box{

    width:100%;

    max-width:600px;

    margin:0 auto;

    background:#111;

    border-radius:12px;

    overflow:hidden;
}

#video{

    width:100%;

    display:block;

    transform:scaleX(-1);
}


/* ================= CAMERA STATUS ================= */

.camera-status{

    text-align:center;

    margin-top:12px;

    color:#777;

    font-size:14px;
}


/* ================= BUTTON ================= */

.button-container{

    display:flex;

    justify-content:center;

    gap:10px;

    margin-top:20px;

    flex-wrap:wrap;
}

button{

    border:none;

    padding:12px 22px;

    border-radius:8px;

    font-size:14px;

    font-weight:bold;

    cursor:pointer;

    transition:0.3s;
}

.open-button{

    background:#3949db;

    color:white;
}

.open-button:hover{

    background:#2836c5;
}

.capture-button{

    background:#2e7d32;

    color:white;
}

.capture-button:hover{

    background:#256628;
}

.capture-button:disabled{

    background:#aaa;

    cursor:not-allowed;
}

.stop-button{

    background:#d32f2f;

    color:white;
}

.stop-button:hover{

    background:#b71c1c;
}

.save-button{

    background:#3949db;

    color:white;

    width:100%;

    margin-top:20px;
}

.save-button:hover{

    background:#2836c5;
}

.save-button:disabled{

    background:#aaa;

    cursor:not-allowed;
}


/* ================= PROGRESS ================= */

.progress-container{

    max-width:600px;

    margin:20px auto 0;

    text-align:center;
}

.progress-text{

    font-size:14px;

    color:#555;

    margin-bottom:8px;
}

.progress-bar{

    width:100%;

    height:10px;

    background:#eee;

    border-radius:10px;

    overflow:hidden;
}

.progress{

    height:100%;

    width:0%;

    background:#3949db;

    transition:0.3s;
}


/* ================= PREVIEW ================= */

.preview{

    display:none;

    margin-top:25px;
}

.preview h3{

    text-align:center;

    color:#3949db;

    font-size:18px;

    margin-bottom:15px;
}

.preview-grid{

    display:grid;

    grid-template-columns:repeat(3,1fr);

    gap:10px;
}

.preview-grid img{

    width:100%;

    height:130px;

    object-fit:cover;

    border-radius:8px;

    border:2px solid #eee;
}


/* ================= MESSAGE ================= */

.message{

    display:none;

    margin-top:20px;

    padding:12px;

    border-radius:8px;

    text-align:center;

    font-size:14px;
}

.success{

    background:#e8f5e9;

    color:#2e7d32;
}

.error{

    background:#ffebee;

    color:#c62828;
}


/* ================= BACK BUTTON ================= */

.back-button{

    display:block;

    text-align:center;

    margin-top:20px;

    color:#555;

    text-decoration:none;

    font-size:14px;
}

.back-button:hover{

    color:#3949db;
}


/* ================= RESPONSIVE ================= */

@media(max-width:700px){

    .content{

        padding:20px;
    }

    .registration-card{

        padding:25px 20px;
    }

    .sidebar{

        width:230px;
    }

    .sidebar.closed{

        right:-230px;
    }

    .preview-grid{

        grid-template-columns:repeat(2,1fr);
    }

}

</style>

</head>


<body>


<!-- ================= HEADER ================= -->

<div class="header">


    <div class="header-left">


        <img
            src="images/kemas.png"
            class="logo-kemas"
        >


        <div class="title">

            <h2>
                eAttendance
            </h2>

            <p>
                TABIKA KEMAS
            </p>

        </div>


    </div>


    <button
        class="menu-btn"
        onclick="toggleSidebar()">

        ☰

    </button>


</div>



<!-- ================= SIDEBAR ================= -->

<div
    class="sidebar closed"
    id="sidebar">


    <div class="sidebar-title">

        Face ID Attendance

    </div>


    <ul>


        <li>

            <a href="dashboard.php">

                <span class="menu-icon">
                    🏠
                </span>

                Dashboard

            </a>

        </li>


        <li>

            <a href="registration.php">

                <span class="menu-icon">
                    👨‍🎓
                </span>

                Student Registration

            </a>

        </li>


        <li>

            <a href="ambil_kehadiran.php">

                <span class="menu-icon">
                    📷
                </span>

                Take Attendance

            </a>

        </li>


        <li>

            <a href="rekod_kehadiran.php">

                <span class="menu-icon">
                    📋
                </span>

                Rekod Kehadiran

            </a>

        </li>


        <li>

            <a href="status_kehadiran.php">

                <span class="menu-icon">
                    📅
                </span>

                Status Kehadiran

            </a>

        </li>


        <li>

            <a href="laporan_kehadiran.php">

                <span class="menu-icon">
                    📊
                </span>

                Laporan Kehadiran

            </a>

        </li>


        <li>

            <a href="logout.php">

                <span class="menu-icon">
                    🚪
                </span>

                Logout

            </a>

        </li>


    </ul>

</div>



<!-- ================= OVERLAY ================= -->

<div
    class="overlay"
    id="overlay"
    onclick="toggleSidebar()">
</div>



<!-- ================= CONTENT ================= -->

<div class="content">


    <div class="registration-card">


        <!-- TITLE -->

        <h2>
            Student Registration
        </h2>


        <p class="description">

            Register a new student and capture face images

        </p>



        <!-- STUDENT ID -->

        <div class="form-group">

            <label for="student_id">

                Student ID

            </label>


            <input
                type="text"
                id="student_id"
                placeholder="Example: M001"
            >

        </div>



        <!-- STUDENT NAME -->

        <div class="form-group">

            <label for="student_name">

                Student Name

            </label>


            <input
                type="text"
                id="student_name"
                placeholder="Example: Ali Ahmad"
            >

        </div>



        <!-- CLASS -->

        <div class="form-group">

            <label for="student_class">

                Class

            </label>


            <input
                type="text"
                id="student_class"
                placeholder="Example: TABIKA A"
            >

        </div>



        <!-- CAMERA TITLE -->

        <h3 class="camera-title">

            Face Registration

        </h3>



        <!-- CAMERA -->

        <div class="camera-box">

            <video
                id="video"
                autoplay
                playsinline>
            </video>

        </div>



        <!-- CAMERA STATUS -->

        <div
            class="camera-status"
            id="cameraStatus">

            Camera belum dibuka.

        </div>



        <!-- CAMERA BUTTONS -->

        <div class="button-container">


            <button
                type="button"
                class="open-button"
                onclick="startCamera()">

                Open Camera

            </button>


            <button
                type="button"
                class="capture-button"
                id="captureButton"
                onclick="captureImage()"
                disabled>

                Capture Image

            </button>


            <button
                type="button"
                class="stop-button"
                onclick="stopCamera()">

                Stop Camera

            </button>


        </div>



        <!-- PROGRESS -->

        <div class="progress-container">


            <div
                class="progress-text"
                id="progressText">

                0 / 6 images captured

            </div>


            <div class="progress-bar">

                <div
                    class="progress"
                    id="progress">
                </div>

            </div>


        </div>



        <!-- PREVIEW -->

        <div
            class="preview"
            id="preview">


            <h3>
                Captured Images
            </h3>


            <div
                class="preview-grid"
                id="previewGrid">
            </div>


        </div>



        <!-- MESSAGE -->

        <div
            class="message"
            id="message">
        </div>



        <!-- SAVE BUTTON -->

        <button
            type="button"
            class="save-button"
            id="saveButton"
            onclick="saveStudent()"
            disabled>

            Save Student

        </button>



        <!-- BACK -->

        <a
            href="dashboard.php"
            class="back-button">

            ← Back to Dashboard

        </a>


    </div>


</div>



<!-- ================= JAVASCRIPT ================= -->

<script>


let stream = null;

let capturedImages = [];

const MAX_IMAGES = 6;


/* ================= SIDEBAR ================= */

function toggleSidebar(){

    const sidebar =
        document.getElementById("sidebar");

    const overlay =
        document.getElementById("overlay");


    sidebar.classList.toggle("closed");

    overlay.classList.toggle("active");

}



/* ================= START RAPOO CAMERA ================= */

async function startCamera(){

    const video =
        document.getElementById("video");

    const captureButton =
        document.getElementById("captureButton");

    const status =
        document.getElementById("cameraStatus");

    try{

        if(!navigator.mediaDevices ||
           !navigator.mediaDevices.getUserMedia){

            status.innerHTML =
                "Camera tidak disokong oleh browser.";

            return;
        }


        status.innerHTML =
            "Mencari Rapoo Webcam...";


        /* ==========================================
           STEP 1
           Buka camera sementara untuk dapatkan
           senarai camera dan nama device
        ========================================== */

        let tempStream =
            await navigator.mediaDevices.getUserMedia({

                video:true,
                audio:false

            });


        /* ==========================================
           STEP 2
           Dapatkan semua camera
        ========================================== */

        const devices =
            await navigator.mediaDevices.enumerateDevices();


        /* ==========================================
           STEP 3
           Cari Rapoo Webcam
        ========================================== */

        const cameras =
            devices.filter(
                device =>
                    device.kind === "videoinput"
            );


        console.log("Senarai camera:");

        cameras.forEach(
            camera => {

                console.log(
                    camera.label,
                    camera.deviceId
                );

            }
        );


        /* ==========================================
           Cari camera yang mempunyai nama RAPOO
        ========================================== */

        const rapooCamera =
            cameras.find(
                camera =>
                    camera.label
                        .toLowerCase()
                        .includes("rapoo")
            );


        /* ==========================================
           Tutup camera sementara
        ========================================== */

        tempStream
            .getTracks()
            .forEach(
                track =>
                    track.stop()
            );


        /* ==========================================
           RAPOO TIDAK DIJUMPAI
        ========================================== */

        if(!rapooCamera){

            status.innerHTML =
                "Rapoo Webcam tidak dijumpai. Sila pastikan webcam Rapoo disambungkan.";

            console.log(
                "Camera yang dijumpai:",
                cameras
            );

            return;
        }


        /* ==========================================
           PAPAR NAMA CAMERA
        ========================================== */

        console.log(
            "Rapoo Webcam dipilih:",
            rapooCamera.label
        );


        status.innerHTML =
            "Membuka Rapoo Webcam...";


        /* ==========================================
           STEP 4
           Buka RAPOO secara khusus menggunakan
           deviceId
        ========================================== */

        stream =
            await navigator.mediaDevices.getUserMedia({

                video:{
                    deviceId:{
                        exact:
                            rapooCamera.deviceId
                    },

                    width:{
                        ideal:640
                    },

                    height:{
                        ideal:480
                    }
                },

                audio:false

            });


        /* ==========================================
           PAPAR RAPOO PADA VIDEO
        ========================================== */

        video.srcObject =
            stream;


        await video.play();


        /* ==========================================
           ENABLE CAPTURE BUTTON
        ========================================== */

        captureButton.disabled =
            false;


        status.innerHTML =
            "Rapoo Webcam sedang digunakan. Pastikan muka berada di tengah.";


        console.log(
            "BERJAYA: Rapoo Webcam digunakan."
        );

    }

    catch(error){

        console.log(
            "Camera Error:",
            error
        );


        if(error.name === "NotAllowedError"){

            status.innerHTML =
                "Camera permission ditolak. Sila tekan Allow.";

        }

        else if(error.name === "NotFoundError"){

            status.innerHTML =
                "Rapoo Webcam tidak dijumpai.";

        }

        else if(error.name === "NotReadableError"){

            status.innerHTML =
                "Rapoo Webcam sedang digunakan oleh aplikasi lain.";

        }

        else{

            status.innerHTML =
                "Tidak dapat membuka Rapoo Webcam.";

        }

    }

}



/* ================= STOP CAMERA ================= */

function stopCamera(){

    if(stream){

        stream.getTracks().forEach(
            function(track){

                track.stop();

            }
        );

        stream = null;

    }


    document.getElementById("video").srcObject =
        null;


    document.getElementById("captureButton").disabled =
        true;


    document.getElementById("cameraStatus").innerHTML =
        "Camera telah ditutup.";

}



/* ================= CAPTURE IMAGE ================= */

function captureImage(){

    const studentID =
        document.getElementById("student_id").value.trim();

    const studentName =
        document.getElementById("student_name").value.trim();

    const studentClass =
        document.getElementById("student_class").value.trim();


    const video =
        document.getElementById("video");


    if(studentID === ""){

        showMessage(
            "Sila masukkan Student ID.",
            "error"
        );

        return;

    }


    if(studentName === ""){

        showMessage(
            "Sila masukkan Student Name.",
            "error"
        );

        return;

    }


    if(studentClass === ""){

        showMessage(
            "Sila masukkan Class.",
            "error"
        );

        return;

    }


    if(!stream){

        showMessage(
            "Sila buka camera dahulu.",
            "error"
        );

        return;

    }


    if(capturedImages.length >= MAX_IMAGES){

        showMessage(
            "6 gambar sudah diambil.",
            "error"
        );

        return;

    }


    /* CREATE CANVAS */

    const canvas =
        document.createElement("canvas");


    canvas.width =
        video.videoWidth;

    canvas.height =
        video.videoHeight;


    const context =
        canvas.getContext("2d");


    /* MIRROR */

    context.translate(
        canvas.width,
        0
    );

    context.scale(
        -1,
        1
    );


    context.drawImage(
        video,
        0,
        0,
        canvas.width,
        canvas.height
    );


    /* IMAGE */

    const image =
        canvas.toDataURL(
            "image/jpeg",
            0.85
        );


    capturedImages.push(image);


    /* PREVIEW */

    const img =
        document.createElement("img");


    img.src =
        image;


    document
        .getElementById("previewGrid")
        .appendChild(img);


    document
        .getElementById("preview")
        .style.display =
        "block";


    /* UPDATE */

    const count =
        capturedImages.length;


    const percentage =
        (count / MAX_IMAGES) * 100;


    document
        .getElementById("progress")
        .style.width =
        percentage + "%";


    document
        .getElementById("progressText")
        .innerHTML =
        count +
        " / " +
        MAX_IMAGES +
        " images captured";


    /* COMPLETE */

    if(count === MAX_IMAGES){

        document
            .getElementById("captureButton")
            .disabled =
            true;


        document
            .getElementById("saveButton")
            .disabled =
            false;


        document
            .getElementById("cameraStatus")
            .innerHTML =
            "6 gambar berjaya diambil. Klik Save Student.";

        showMessage(
            "6 gambar telah berjaya diambil.",
            "success"
        );

    }

    else{

        showMessage(
            "Gambar " +
            count +
            " berjaya diambil.",
            "success"
        );

    }

}



/* ================= SAVE STUDENT ================= */

async function saveStudent(){

    const studentID =
        document.getElementById("student_id").value.trim();

    const studentName =
        document.getElementById("student_name").value.trim();

    const studentClass =
        document.getElementById("student_class").value.trim();


    const saveButton =
        document.getElementById("saveButton");


    if(capturedImages.length !== MAX_IMAGES){

        showMessage(
            "Sila ambil 6 gambar terlebih dahulu.",
            "error"
        );

        return;

    }


    saveButton.disabled =
        true;


    saveButton.innerHTML =
        "Saving...";


    try{

        const response =
            await fetch(
                "save_student.php",
                {

                    method:"POST",

                    headers:{
                        "Content-Type":
                            "application/json"
                    },

                    body:JSON.stringify({

                        student_id:
                            studentID,

                        student_name:
                            studentName,

                        student_class:
                            studentClass,

                        images:
                            capturedImages

                    })

                }
            );


        const result =
            await response.json();


        if(result.success){

            showMessage(
                result.message,
                "success"
            );


            stopCamera();


            saveButton.innerHTML =
                "Student Saved";


        }

        else{

            showMessage(
                result.message,
                "error"
            );


            saveButton.disabled =
                false;


            saveButton.innerHTML =
                "Save Student";

        }

    }

    catch(error){

        console.log(error);


        showMessage(
            "Ralat semasa menyimpan data.",
            "error"
        );


        saveButton.disabled =
            false;


        saveButton.innerHTML =
            "Save Student";

    }

}



/* ================= MESSAGE ================= */

function showMessage(
    text,
    type
){

    const message =
        document.getElementById("message");


    message.innerHTML =
        text;


    message.className =
        "message " + type;


    message.style.display =
        "block";

}

</script>


</body>

</html>