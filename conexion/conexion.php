<?php


   $conn = mysqli_connect("localhost","root","1234","almacen_db","3307");
    $conn->set_charset("utf8mb4");
  if ($conn->connect_error) {
    die("Error: Imposible conectarse: " . $conn->connect_error);
  }


 

// $conn->close();

  ?>
