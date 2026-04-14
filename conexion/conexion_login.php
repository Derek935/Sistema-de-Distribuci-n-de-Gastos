<?php
if(!empty($_POST["btnlogin"])){
        if(empty($_POST["usuario"]) || empty($_POST["password"])){
            echo "<script>alert('Por favor, completa todos los campos');</script>";
        } 
        else {
        
        $usuario = $_POST["usuario"];
        $clave = $_POST["password"];
        
        $sql = $conn->query ("SELECT * FROM nom_encargados WHERE nombres='$usuario' AND contraseña='$clave'");
        
        if($datos=$sql->fetch_object()){
            header("Location: registroGastos.php");
            exit();
        } else {
            echo '<div>"Usuario o contraseña incorrectos"</div>';
        }
    }
    }
?>