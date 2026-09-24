<?php

include "db.php";


// =====================================
// GET STUDENT
// =====================================

$sql = "SELECT student_id, name FROM student ORDER BY name ASC";

$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Notice Ketidakhadiran</title>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


body {

    font-family: Arial, sans-serif;

    background: #f4f6f9;

    padding: 40px;

}


.container {

    width: 600px;

    margin: auto;

    background: white;

    padding: 30px;

    border-radius: 10px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.1);

}


h1 {

    text-align: center;

    margin-bottom: 10px;

}


.subtitle {

    text-align: center;

    color: #777;

    margin-bottom: 30px;

}


.form-group {

    margin-bottom: 20px;

}


label {

    display: block;

    margin-bottom: 8px;

    font-weight: bold;

}


select,
input,
textarea {

    width: 100%;

    padding: 12px;

    border: 1px solid #ccc;

    border-radius: 6px;

    font-size: 15px;

}


textarea {

    height: 120px;

}


button {

    width: 100%;

    padding: 13px;

    background: #2c7be5;

    color: white;

    border: none;

    border-radius: 6px;

    font-size: 16px;

    cursor: pointer;

}


button:hover {

    background: #1a68d1;

}


</style>

</head>


<body>


<div class="container">

<h1>Notice Ketidakhadiran</h1>

<p class="subtitle">

Surat Makluman Ketidakhadiran Murid

</p>


<form method="POST" action="surat.php">


<!-- NAMA MURID -->

<div class="form-group">

<label>

Nama Murid

</label>


<select name="student_id" required>

<option value="">

-- Pilih Nama Murid --

</option>


<?php

if ($result && $result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {

?>

<option value="<?php echo $row['student_id']; ?>">

<?php echo htmlspecialchars($row['name']); ?>

</option>

<?php

    }

}

?>

</select>

</div>


<!-- TARIKH -->

<div class="form-group">

<label>

Tarikh Tidak Hadir

</label>


<input

type="date"

name="absence_date"

required

>

</div>


<!-- SEBAB -->

<div class="form-group">

<label>

Sebab Ketidakhadiran

</label>


<textarea

name="reason"

placeholder="Contoh: Anak tidak sihat dan perlu mendapatkan rawatan."

required

></textarea>

</div>


<button type="submit">

Papar Surat

</button>


</form>


</div>


</body>

</html>