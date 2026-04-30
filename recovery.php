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
                        // ⚠️ OPCION INSEGURA: Mostrar contraseña
                        // Solo funciona si la contraseña NO está hasheada
                        // Para demo, necesitamos guardar contraseña en texto plano (NO RECOMENDADO)
                        
                        // Si la contraseña está hasheada, NO podemos recuperarla
                        // Mostramos mensaje explicativo
                        $mostrar_password = true;
                        $success = "✅ Palabra de recuperación correcta!";
                        //$password_recuperada = "La contraseña está encriptada por seguridad. No puede ser mostrada.";
                        
                        // Para fines educativos, si quieres mostrar la contraseña real:
                        // Debes guardarla en texto plano en la BD (MUY INSEGURO)
                         $password_recuperada = $user['password_hash'];
                    }else {
                    $error = "❌ Palabra de recuperación incorrecta.";
                }

                }else {
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
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/styles.css" />
    <script src="js/app.js"></script>
    <title>Soluciones de Tecnología Grupo Dos</title>
  </head>
  <style></style>
  <body>
    <header>Soluciones de Tecnología Grupo Dos</header>

    <div class="container">
      <div class="login-box">
        <form method="post" action="">
        <h2>Recuperar Contraseña</h2>
        
        <p class="subtitle">Ingresa tus credenciales para acceder al sistema</p>

        <label>Usuario</label>
        <input name="usuario" placeholder="Número de empleado / Boleta" />

        <label>Correo</label>
        <input name="palabra" placeholder="Ingresa tu correo"/>
        <input name="btnlogin" class="btn" type="submit" value ="Recuperar">
        <?php if($mostrar_password): ?>
                <div class="warning">
                    ⚠️ <strong>Importante:</strong> Por seguridad, las contraseñas están encriptadas. 
                    La opción recomendada es restablecer la contraseña.
                </div>
            <?php endif; ?>

          <?php if(isset($password_recuperada) && $password_recuperada !== "La contraseña está encriptada por seguridad. No puede ser mostrada."): ?>
                <div class="password-display">
                    <strong>Tu Contraseña es:</strong>
                    <div class="password"><?php echo htmlspecialchars($password_recuperada); ?></div>
                    <small>⚠️ No compartas esta contraseña con nadie</small>
                </div>
            <?php endif; ?>  
             <div style="text-align: center; margin-top: 20px;">
                <a href="index.php" class="btn">🔐 Ir al Login</a>
            </div>
      </form>

          
      </div>
    </div>

    <script></script>
  </body>
</html>