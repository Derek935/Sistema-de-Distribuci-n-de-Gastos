<?php 
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: registroGastos.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['usuario']);
    $password = $_POST['password'];
    
    if (!empty($username) && !empty($password)) {
        require 'conexion/conexion.php';
        
        $stmt = $pdo->prepare("SELECT * FROM usuario WHERE nombre = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
       


        if ($password == $user['password_hash']) {
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['username'] = $user['nombre'];
            
            
            header("Location: registroGastos.php");
            exit();
        } else {
            $error = "Usuario o contraseña incorrectos.";
        }
    } else {
        $error = "Por favor complete todos los campos.";
    }
}
?>

<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/styles.css" />
    <script src="js/app.js"></script>
    <title>Soluciones de Tecnología Grupo Dos</title>
  </head>
  <body>
    <header>Soluciones de Tecnología Grupo Dos</header>

    <div class="container">
      <div class="login-box">
        <h2>→ Iniciar Sesión</h2>
      
        <?php if($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post">
        
        <p class="subtitle">Ingresa tus credenciales para acceder al sistema</p>

        <label>Usuario</label>
        <input name="usuario" placeholder="Ingresa tu usuario" />

        <label>Contraseña</label>
        <input name="password" type="password" placeholder="Ingresa tu contraseña"/>
        <input name="btnlogin" class="btn" type="submit" value ="→ Ingresar">
        <a href="recovery.php">Recuperar Contraseña</a>
        </form>

      </div>
    </div>

    <script></script>
  </body>
</html>
