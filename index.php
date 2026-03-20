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
        <h2>→ Iniciar Sesión</h2>
        <?php 
          include ("conexion/conexion.php"); 
          include ("conexion/conexion_login.php"); 
          
          
          ?>
        <p class="subtitle">Ingresa tus credenciales para acceder al sistema</p>

        <label>Usuario</label>
        <input name="usuario" placeholder="Número de empleado / Boleta" />

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
