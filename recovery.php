<?php 
session_start();

require 'conexion/conexion.php';

$error = '';
$success = '';
$usuario = '';
$palabra = '';
$mostrar_password = false;
$password_recuperada = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario']);
    $palabra = trim($_POST['palabra']);
    $accion = $_POST['accion'] ?? 'recuperar';

    if (!empty($usuario) && !empty($palabra)) {
        
        // Buscar usuario por nombre o email
        $stmt = $pdo->prepare("SELECT id_usuario, nombre, password_hash, correo FROM usuario WHERE nombre = ? ");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Verificar palabra de recuperación
            if ($palabra === $user['correo']) {
                
                if ($accion === 'recuperar') {
                    $mostrar_password = true;
                    $success = "✅ Palabra de recuperación correcta!";
                    $password_recuperada = $user['password_hash'];
                } else {
                    $error = "❌ Palabra de recuperación incorrecta.";
                }
            } else {
                $error = "❌ Usuario no encontrado.";
            }
        }
    }
}
?>

<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/styles.css" />
    <title>Soluciones de Tecnología Grupo Dos</title>
  </head>
  <body class="recovery-body">
    <header class="recovery-header">Soluciones de Tecnología Grupo Dos</header>

    <div class="recovery-container">
      <div class="recovery-login-box">
        <form method="post" action="">
          <h2> Recuperar Contraseña</h2>
          <p class="recovery-subtitle">Ingresa tu usuario y correo para recuperar el acceso</p>

          <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>
          
          <?php if($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
          <?php endif; ?>

          <div class="form-group">
            <label for="usuario">Usuario</label>
            <input 
              type="text" 
              id="usuario"
              name="usuario" 
              placeholder="Nombre de empleado" 
              value="<?php echo htmlspecialchars($usuario); ?>"
              required
              autocomplete="username"
            />
          </div>

          <div class="form-group">
            <label for="palabra">Correo Electrónico</label>
            <input 
              type="email" 
              id="palabra"
              name="palabra" 
              placeholder="Ingresa tu correo registrado"
              value="<?php echo htmlspecialchars($palabra); ?>"
              required
              autocomplete="email"
            />
          </div>
          
                    <input name="accion" type="hidden" value="recuperar">
          
          
          <div class="text-center" style="margin: 25px 0;">
            <button type="submit" name="btnlogin" class="btn" style="min-width: 200px;">
              Recuperar Contraseña
            </button>
          </div>
          
          <?php if($mostrar_password): ?>
            <div class="alert alert-warning">
              <strong>Importante:</strong> Por seguridad, las contraseñas están encriptadas. 
              La opción recomendada es restablecer la contraseña.
            </div>
          <?php endif; ?>

          <?php if(isset($password_recuperada) && !empty($password_recuperada)): ?>
            <div class="recovery-password-display">
              <strong>Tu Contraseña:</strong>
              <div class="recovery-password"><?php echo htmlspecialchars($password_recuperada); ?></div>
              <small>No compartas esta contraseña con nadie</small>
            </div>
          <?php endif; ?>  
          
          
          <div class="text-center" style="margin-top: 15px;">
            <a href="index.php" class="btn btn-secondary" style="min-width: 200px;">
              Ir al Login
            </a>
          </div>
        </form>
      </div>
    </div>
  </body>
</html>