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

 echo 'Conectados a la base.<br>';

 $result = $conn->query("SELECT id_cargo FROM nom_encargados");

 echo "Número de registros: $result->num_rows";

 $result->close();

 $conn->close();

  ?>
