<?php
$conn = mysqli_connect("localhost", "root", "", "fitness");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

?>