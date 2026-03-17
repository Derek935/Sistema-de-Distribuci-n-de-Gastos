<?php

   $servidor = "127.0.0.1";
   $usuario = "root";
   $clave = "1234";
   $basedeDatos = "almacen_db";

   $conn = mysqli_connect($servidor,$usuario,$clave,$basedeDatos);
    $conn->set_charset("utf8mb4");
  if ($conn->connect_error) {
    die("Error: Imposible conectarse: " . $conn->connect_error);
  }

 

 $conn->close();

  ?>
