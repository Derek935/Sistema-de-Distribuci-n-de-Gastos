<?php

 $conn = new mysqli("208.109.17.48", "root", "1234", "almacen_db");

  if ($conn->connect_error) {
    die("Error: Imposible conectarse: " . $conn->connect_error);
  }

 echo 'Conectados a la base.<br>';

 $result = $conn->query("SELECT id_cargo FROM nom_encargados");

 echo "Número de registros: $result->num_rows";

 $result->close();

 $conn->close();

  ?>
