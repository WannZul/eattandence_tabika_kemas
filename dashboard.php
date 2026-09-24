<?php  
session_start();  
  
/*  
if(!isset($_SESSION['username'])){  
    header("Location: login.php");  
    exit();  
}  
*/  
  
  
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
  
  
// ==========================================  
// CHECK DATABASE  
// ==========================================  
  
if ($conn->connect_error) {  
  
    die(  
        "Database connection failed: " .  
        $conn->connect_error  
    );  
  
}  
  
  
// ==========================================  
// CURRENT DATE / SELECTED DATE 
// ========================================== 
  
$today = date("Y-m-d"); 
  
if(isset($_GET['date']) && !empty($_GET['date'])){ 
 
    $today = $_GET['date']; 
 
} 
 
  
// ==========================================  
// DISPLAY DATE  
// ==========================================  
  
$display_date = date(  
    "l, d F Y", 
    strtotime($today) 
);  
  
  
// ==========================================  
// TOTAL STUDENTS  
// ==========================================  
  
$total_students = 0;  
  
$sql_total = "  
    SELECT COUNT(*) AS total  
    FROM student  
";  
  
$result_total = $conn->query(  
    $sql_total  
);  
  
if ($result_total) {  
  
    $row_total =  
        $result_total->fetch_assoc();  
  
    $total_students =  
        $row_total['total'];  
  
}  
  
  
// ==========================================  
// PRESENT STUDENTS 
// ==========================================  
  
$present_students = 0;  
  
$sql_present = "  
    SELECT COUNT(DISTINCT student_id) AS present  
    FROM attendance  
    WHERE date = '$today'  
    AND (  
        status = 'Hadir'  
        OR status = 'Present'  
    )  
";  
  
$result_present = $conn->query(  
    $sql_present  
);  
  
if ($result_present) {  
  
    $row_present =  
        $result_present->fetch_assoc();  
  
    $present_students =  
        $row_present['present'];  
  
}  
  
  
// ==========================================  
// ABSENT STUDENTS 
// ==========================================  
  
$absent_students = 0;  
  
$sql_absent = "  
    SELECT COUNT(DISTINCT student_id) AS absent  
    FROM attendance  
    WHERE date = '$today'  
    AND (  
        status = 'Tidak Hadir'  
        OR status = 'Absent'  
    )  
";  
  
$result_absent = $conn->query(  
    $sql_absent  
);  
  
if ($result_absent) {  
  
    $row_absent =  
        $result_absent->fetch_assoc();  
  
    $absent_students =  
        $row_absent['absent'];  
  
}  
  
  
// ==========================================  
// PREVENT NEGATIVE NUMBER  
// ==========================================  
  
if ($absent_students < 0) {  
  
    $absent_students = 0;  
  
}  
  
  
// ==========================================  
// ATTENDANCE RATE  
// ==========================================  
  
if ($total_students > 0) {  
  
    $attendance_rate =  
        round(  
            ($present_students /  
            $total_students) * 100  
        );  
  
} else {  
  
    $attendance_rate = 0;  
  
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
  
<title>Dashboard - eAttendance</title>  
  
  
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
  
    box-shadow:  
  
        0 2px 8px  
  
        rgba(0,0,0,0.2);  
  
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
  
    background:  
  
        rgba(255,255,255,0.15);  
  
}  
  
  
/* ================= RIGHT NAVIGATION ================= */  
  
.sidebar{  
  
    position:fixed;  
  
    top:90px;  
  
    right:0;  
  
    width:260px;  
  
    height:  
  
        calc(100vh - 90px);  
  
    background:white;  
  
    box-shadow:  
  
        -4px 0 15px  
  
        rgba(0,0,0,0.15);  
  
    z-index:999;  
  
    transition:0.3s;  
  
    overflow:hidden;  
  
}  
  
  
/* ================= CLOSED ================= */  
  
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
  
    border-bottom:  
  
        1px solid #ddd;  
  
}  
  
  
/* ================= MENU ================= */  
  
.sidebar ul{  
  
    list-style:none;  
  
}  
  
  
.sidebar ul li{  
  
    border-bottom:  
  
        1px solid #eee;  
  
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
  
    height:  
  
        calc(100vh - 90px);  
  
    background:  
  
        rgba(0,0,0,0.25);  
  
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
  
    min-height:  
  
        calc(100vh - 90px);  
  
}  
  
  
/* ================= WELCOME ================= */  
  
.welcome{  
  
    background:white;  
  
    border-radius:15px;  
  
    padding:25px 30px;  
  
    box-shadow:  
  
        0 3px 10px  
  
        rgba(0,0,0,0.08);  
  
    margin-bottom:25px;  
  
    display:flex;  
  
    justify-content:space-between;  
  
    align-items:center;  
  
}  
  
  
.welcome-text h2{  
  
    color:#3949db;  
  
    font-size:28px;  
  
    margin-bottom:8px;  
  
}  
  
  
.welcome-text p{  
  
    color:#666;  
  
    font-size:15px;  
  
    margin-bottom:12px;  
  
}  
  
  
.date{  
  
    color:#777;  
  
    font-size:14px;  
  
}  
  
 
/* ================= CALENDAR ================= */ 
 
.date input{ 
 
    border:1px solid #ddd; 
 
    padding:8px 10px; 
 
    border-radius:7px; 
 
    font-size:14px; 
 
    color:#555; 
 
    cursor:pointer; 
 
    background:white; 
 
} 
 
  
.date input:focus{ 
 
    outline:none; 
 
    border-color:#3949db; 
 
} 
 
 
.welcome-icon{  
  
    width:90px;  
  
    height:90px;  
  
    background:#eef0ff;  
  
    border-radius:50%;  
  
    display:flex;  
  
    justify-content:center;  
  
    align-items:center;  
  
    font-size:45px;  
  
}  
  
  
/* ================= STATISTICS ================= */  
  
.stats{  
  
    display:grid;  
  
    grid-template-columns:  
  
        repeat(4,1fr);  
  
    gap:20px;  
  
    margin-bottom:25px;  
  
}  
  
  
/* ================= CARD ================= */  
  
.card{  
  
    background:white;  
  
    padding:22px;  
  
    border-radius:15px;  
  
    box-shadow:  
  
        0 3px 10px  
  
        rgba(0,0,0,0.08);  
  
    display:flex;  
  
    align-items:center;  
  
    gap:15px;  
  
    transition:0.3s;  
  
}  
  
  
.card:hover{  
  
    transform:  
  
        translateY(-3px);  
  
    box-shadow:  
  
        0 5px 15px  
  
        rgba(0,0,0,0.12);  
  
}  
  
  
/* ================= CARD ICON ================= */  
  
.card-icon{  
  
    width:55px;  
  
    height:55px;  
  
    border-radius:12px;  
  
    background:#eef0ff;  
  
    display:flex;  
  
    justify-content:center;  
  
    align-items:center;  
  
    font-size:28px;  
  
}  
  
  
/* ================= CARD NUMBER ================= */  
  
.card h3{  
  
    color:#333;  
  
    font-size:25px;  
  
    margin-bottom:4px;  
  
}  
  
  
/* ================= CARD TEXT ================= */  
  
.card p{  
  
    color:#777;  
  
    font-size:13px;  
  
}  
  
  
/* ================= MAIN SECTION ================= */  
  
.main-section{  
  
    width:100%;  
  
    margin-bottom:25px;  
  
}  
  
  
/* ================= ATTENDANCE BOX ================= */  
  
.attendance-box{  
  
    background:white;  
  
    padding:35px;  
  
    border-radius:15px;  
  
    box-shadow:  
  
        0 3px 10px  
  
        rgba(0,0,0,0.08);  
  
    text-align:center;  
  
}  
  
  
.attendance-box h2{  
  
    color:#3949db;  
  
    font-size:24px;  
  
    margin-bottom:10px;  
  
}  
  
  
.attendance-box p{  
  
    color:#666;  
  
    font-size:15px;  
  
    line-height:1.6;  
  
    margin-bottom:25px;  
  
}  
  
  
/* ================= BUTTON ================= */  
  
.take-button{  
  
    display:inline-block;  
  
    padding:13px 30px;  
  
    background:#3949db;  
  
    color:white;  
  
    text-decoration:none;  
  
    border-radius:9px;  
  
    font-size:15px;  
  
    font-weight:bold;  
  
    transition:0.3s;  
  
}  
  
  
.take-button:hover{  
  
    background:#2836c5;  
  
    transform:  
  
        translateY(-2px);  
  
    box-shadow:  
  
        0 5px 12px  
  
        rgba(57,73,219,0.25);  
  
}  
  
 
/* ================= MANUAL ATTENDANCE ================= */ 
 
.manual-button{  
  
    display:inline-block;  
  
    padding:13px 30px;  
  
    background:#3949db;  
  
    color:white;  
  
    text-decoration:none;  
  
    border-radius:9px;  
  
    font-size:15px;  
  
    font-weight:bold;  
  
    transition:0.3s;  
  
    margin-left:10px;  
  
}  
  
  
.manual-button:hover{  
  
    background:#2836c5;  
  
    transform:  
  
        translateY(-2px);  
  
    box-shadow:  
  
        0 5px 12px  
  
        rgba(57,73,219,0.25);  
  
}  

.manual-button:hover{  
  
    background:#2836c5;  
  
    transform:  
        translateY(-2px);  
  
    box-shadow:  
        0 5px 12px  
        rgba(57,73,219,0.25);  
  
}

/* ================= TELEGRAM NOTIFICATION BUTTON ================= */

.notification-btn{
    display:inline-block;
    padding:13px 30px;
    background:#3949db;
    color:white;
    text-decoration:none;
    border:none;
    border-radius:9px;
    font-size:15px;
    font-weight:bold;
    cursor:pointer;
    transition:0.3s;
    margin-left:10px;
}

.notification-btn:hover{
    background:#2838c5;
    transform:translateY(-2px);
    box-shadow:0 5px 12px rgba(57,73,219,0.25);
}
  
  
  
/* ================= IMAGE SECTION ================= */  
  
.image-section{  
  
    background:white;  
  
    margin-top:25px;  
  
    padding:30px;  
  
    border-radius:15px;  
  
    box-shadow:  
  
        0 3px 10px  
  
        rgba(0,0,0,0.08);  
  
    text-align:center;  
  
}  
  
  
.image-section h2{  
  
    color:#3949db;  
  
    margin-bottom:20px;  
  
    font-size:24px;  
  
}  
  
  
.image-section img{  
  
    width:100%;  
  
    max-width:1000px;  
  
    height:400px;  
  
    object-fit:cover;  
  
    border-radius:12px;  
  
    display:block;  
  
    margin:0 auto;  
  
}  
  
  
/* ================= FOOTER ================= */  
  
.footer{  
  
    background:white;  
  
    padding:20px;  
  
    width:100%;  
  
    text-align:center;  
  
    margin-top:30px;  
  
    color:#777;  
  
    font-size:13px;  
  
    box-shadow:  
  
        0 -2px 8px  
  
        rgba(0,0,0,0.05);  
  
}  
  
  
/* ================= RESPONSIVE ================= */  
  
@media(max-width:1000px){  
  
    .stats{  
  
        grid-template-columns:  
  
            repeat(2,1fr);  
  
    }  
  
}  
  
  
@media(max-width:700px){  
  
    .content{  
  
        padding:20px;  
  
    }  
  
  
    .welcome{  
  
        flex-direction:column;  
  
        text-align:center;  
  
        gap:15px;  
  
    }  
  
  
    .stats{  
  
        grid-template-columns:1fr;  
  
    }  
  
  
    .sidebar{  
  
        width:230px;  
  
    }  
  
  
    .sidebar.closed{  
  
        right:-230px;  
  
    }  
  
  
    .image-section img{  
  
        height:300px;  
  
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
        onclick="toggleSidebar()"  
    >  
  
        ☰  
  
    </button>  
  
  
</div>  
  
  
<!-- ================= RIGHT NAVIGATION ================= -->  
  
<div  
    class="sidebar closed"  
    id="sidebar"  
>  
  
  
  
    <div class="sidebar-title">  
  
        Face ID Attendance  
  
    </div>  
  
  
    <ul>  
  
  
        <!-- DASHBOARD -->  
  
        <li>  
  
            <a href="dashboard.php">  
  
                <span class="menu-icon">  
                    🏠  
                </span>  
  
                Dashboard  
  
            </a>  
  
        </li>  
  
  
        <!-- STUDENT REGISTRATION -->  
  
        <li>  
  
            <a href="registration.php">  
  
                <span class="menu-icon">  
                    👨‍🎓  
                </span>  
  
                Student Registration  
  
            </a>  
  
        </li>  
  
  
        <!-- TAKE ATTENDANCE -->  
  
        <li>  
  
            <a href="ambil_kehadiran.php">  
  
                <span class="menu-icon">  
                    📷  
                </span>  
  
                Take Attendance  
  
            </a>  
  
        </li>  
  
  
        <!-- MANUAL ATTENDANCE --> 
  
        <li>  
  
            <a href="manual_kehadiran.php">  
  
                <span class="menu-icon">  
                    ✍️  
                </span>  
  
                Manual Attendance  
  
            </a>  
  
        </li>  
  
  
        <!-- ATTENDANCE RECORD -->  
  
        <li>  
  
            <a href="rekod_kehadiran.php">  
  
                <span class="menu-icon">  
                    📋  
                </span>  
  
                Attendance Records  
  
            </a>  
  
        </li>  
  
  
        <!-- ATTENDANCE STATUS -->  
  
        <li>  
  
            <a href="status_kehadiran.php">  
  
                <span class="menu-icon">  
                    📅  
                </span>  
  
                Attendance Status  
  
            </a>  
  
        </li>  
  
  
        <!-- ATTENDANCE REPORT -->  
  
        <li>  
  
            <a href="laporan_kehadiran.php">  
  
                <span class="menu-icon">  
                    📊  
                </span>  
  
                Attendance Report 
  
            </a>  
  
        </li>  
  
  
        <!-- ABSENCE NOTICE -->  
  
        <li>  
  
            <a href="notice.php">  
  
                <span class="menu-icon">  
                    📄  
                </span>  
                Absence Notice
            </a>  
  
        </li>  
  
  
        <!-- LOGOUT -->  
  
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
    onclick="toggleSidebar()"  
>  
</div>  
  
  
<!-- ================= CONTENT ================= -->  
  
<div class="content">  
  
  
    <!-- ================= WELCOME ================= -->  
  
    <div class="welcome">  
  
  
        <div class="welcome-text">  
  
  
            <h2>  
                👋 Welcome to eAttendance  
            </h2>  
  
  
            <p>  
                Face 
        <button
            type="button"
            class="date-btn"
            onclick="changeDate()"
        >

            Papar

        </button>
 
            </p>  
  
  
            <div class="date">  
  
                📅 
  
                <input  
                    type="date"  
                    value="<?php echo $today; ?>" 
                    onchange="changeDate(this.value)" 
                > 
  
            </div>  
  
  
        </div>  
  
  
        <div class="welcome-icon">  
  
            🔐  
  
        </div>  
  
  
    </div>  
  
  
    <!-- ================= STATISTICS ================= -->  
  
    <div class="stats">  
  
  
        <!-- TOTAL STUDENTS -->  
  
        <div class="card">  
  
  
            <div class="card-icon">  
  
                👨‍🎓  
  
            </div>  
  
  
            <div>  
  
                <h3>  
  
                    <?php  
                    echo $total_students;  
                    ?>  
  
                </h3>  
  
                <p>  
  
                    Total Students  
  
                </p>  
  
            </div>  
  
  
        </div>  
  
  
        <!-- PRESENT -->  
  
        <div class="card">  
  
  
            <div class="card-icon">  
  
                ✅  
  
            </div>  
  
  
            <div>  
  
                <h3>  
  
                    <?php  
                    echo $present_students;  
                    ?>  
  
                </h3>  
  
                <p>  
  
                    Present Today  
  
                </p>  
  
            </div>  
  
  
        </div>  
  
  
        <!-- ABSENT -->  
  
        <div class="card">  
  
  
            <div class="card-icon">  
  
                ❌  
  
            </div>  
  
  
            <div>  
  
                <h3>  
  
                    <?php  
                    echo $absent_students;  
                    ?>  
  
                </h3>  
  
                <p>  
  
                    Absent Today  
  
                </p>  
  
            </div>  
  
  
        </div>  
  
  
        <!-- ATTENDANCE RATE -->  
  
        <div class="card">  
  
  
            <div class="card-icon">  
  
                📊  
  
            </div>  
  
  
            <div>  
  
                <h3>  
  
                    <?php  
                    echo $attendance_rate;  
                    ?>%  
  
                </h3>  
  
                <p>  
  
                    Attendance Rate  
  
                </p>  
  
            </div>  
  
  
        </div>  
  
  
    </div>  
  
  
    <!-- ================= TAKE ATTENDANCE ================= -->  
  
    <div class="main-section">  
  
  
        <div class="attendance-box">  
  
  
            <h2>  
                📷 Today's Attendance  
            </h2>  
  
  
            <p>  
  
                Use the Face Recognition system to record  
                student attendance automatically.  
  
            </p>  
  
  
            <a  
                href="ambil_kehadiran.php"  
                class="take-button"  
            >  
  
                📷 Start Face Recognition  
  
            </a> 
  
  
            <!-- MANUAL ATTENDANCE BUTTON --> 
  
            <a  
                href="manual_kehadiran.php"  
                class="manual-button"  
            >  
  
                ✍️ Manual Attendance  
  
            </a> 

            <form method="POST" action="telegram.php" style="display:inline;">

            <button
                type="submit"
                name="send_absence"
                class="notification-btn"
                onclick="return confirm('Hantar notifikasi kepada ibu bapa murid yang tidak hadir hari ini?');"
                >

                🔔 Send Absence Notification

            </button>

</form>
  
  

        </div>  
  
  
    </div>  
  
  
    <!-- ================= IMAGE ================= -->  
  
    <div class="image-section">  
  
  
        <h2>  
            🏫 TABIKA KEMAS  
        </h2>  
  
  
        <img  
            src="images/image1.jpeg"  
            alt="TABIKA KEMAS"  
        >  
  
  
    </div>  
  
  
    <!-- ================= FOOTER ================= -->  
  
    <div class="footer">  
  
        Copyright © 2026 Face ID Attendance System  
  
    </div>  
  
  
</div>  
  
  
<!-- ================= JAVASCRIPT ================= -->  
  
<script>  
  
function toggleSidebar(){  
  
    const sidebar =  
        document.getElementById(  
            "sidebar"  
        );  
  
    const overlay =  
        document.getElementById(  
            "overlay"  
        );  
  
  
    sidebar.classList.toggle(  
        "closed"  
    );  
  
    overlay.classList.toggle(  
        "active"  
    );  
  
} 
 
 
/* ================= CHANGE DATE ================= */ 
 
function changeDate(date){ 
 
    window.location.href =  
        "dashboard.php?date=" + date; 
 
} 
  
</script>  
  
  
</body>  
  
</html>  
  
  
<?php  
  
$conn->close();  
  
?>



