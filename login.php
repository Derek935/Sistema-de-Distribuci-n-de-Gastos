<?php

$mensaje = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $user = $_POST["usuario"];
    $pass = $_POST["password"];

    if($user == "admin" && $pass == "1234"){
        $mensaje = "Bienvenido al sistema";
    }else{
        $mensaje = "Usuario o contraseña incorrectos";
    }

}

?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Soluciones de Tecnología Grupo Dos</title>

<style>

body{
font-family: Arial, Helvetica, sans-serif;
background:#f5f6f8;
margin:0;
}

header{
display:flex;
align-items:center;
padding:20px 40px;
font-size:28px;
font-weight:bold;
border-bottom:1px solid #e5e5e5;
background:white;
}

.container{
display:flex;
justify-content:center;
align-items:center;
height:80vh;
}

.login-box{
background:white;
width:420px;
padding:35px;
border-radius:12px;
box-shadow:0 4px 15px rgba(0,0,0,0.08);
}

.login-box h2{
margin-bottom:5px;
}

.subtitle{
color:#6b7280;
margin-bottom:25px;
}

label{
font-weight:600;
display:block;
margin-top:10px;
}

input{
width:100%;
padding:12px;
margin-top:6px;
margin-bottom:15px;
border-radius:8px;
border:1px solid #d1d5db;
background:#f3f4f6;
}

button{
width:100%;
padding:12px;
background:#020617;
color:white;
border:none;
border-radius:8px;
font-size:16px;
cursor:pointer;
}

button:hover{
background:#111827;
}

.mensaje{
margin-top:15px;
font-weight:bold;
color:#111827;
}

</style>
</head>

<body>

<header>
💳 Soluciones de Tecnología Grupo Dos
</header>

<div class="container">

<div class="login-box">

<h2>→ Iniciar Sesión</h2>
<p class="subtitle">Ingresa tus credenciales para acceder al sistema</p>

<form method="POST">

<label>Usuario</label>
<input name="usuario" placeholder="Número de empleado / Boleta" required>

<label>Contraseña</label>
<input name="password" type="password" placeholder="Ingresa tu contraseña" required>

<button type="submit">→ Ingresar</button>

</form>

<?php
if($mensaje != ""){
    echo "<p class='mensaje'>$mensaje</p>";
}
?>

</div>

</div>

</body>
</html>