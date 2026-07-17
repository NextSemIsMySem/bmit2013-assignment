<?php
$conn = mysqli_connect("localhost", "root", "", "fitnessdb");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

?>