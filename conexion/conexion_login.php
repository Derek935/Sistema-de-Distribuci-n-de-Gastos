<?php
    if(!empty($_POST['login'])){
        if(empty($_POST['usuario']) || empty($_POST['password'])){
            echo "<script>alert('Por favor, completa todos los campos');</script>";
        } 
        else {
        include("conexion/conexion.php");
        $usuario = $_POST['usuario'];
        $clave = $_POST['password'];
        $sql = "SELECT * FROM nom_encargados WHERE nombres='$usuario' AND contraseña='$clave'";
        $result = mysqli_query($conn, $sql);
        if(mysqli_num_rows($result) > 0){
            header("Location: dashboard.php");
            exit();
        } else {
            echo "<script>alert('Usuario o contraseña incorrectos');</script>";
        }
    }
    }
?>