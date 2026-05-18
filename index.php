<?php
// index.php
session_start();

// Si ya está logueado, redirigir según rol
if (isset($_SESSION['user_id'])) {
    // Evitar bucle - verificar si ya estamos en la página de destino
    if ($_SESSION['user_rol'] === 1) {
        header("Location: dashboard.php");
    } else {
        header("Location: registroGastos.php");
    }
    exit();
}


$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        try {
            require 'conexion/conexion.php';
            
            // Buscar usuario CON su rol
            $stmt = $pdo->prepare("SELECT id_usuario, nombre, password_hash, id_rol, correo, estado FROM usuario WHERE nombre = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $password == $user['password_hash']) {
                
                // Verificar que el usuario esté activo
                if ($user['estado'] != 1) {
                    $error = "❌ Tu cuenta está inactiva. Contacta al administrador.";
                } else {
                    // ✅ Guardar TODOS los datos en sesión INCLUYENDO el rol
                    $_SESSION['user_id'] = $user['id_usuario'];
                    $_SESSION['username'] = $user['nombre'];
                    $_SESSION['user_rol'] = $user['id_rol'];  // ← IMPORTANTE
                    $_SESSION['user_email'] = $user['correo'];
                    
                    // 🎯 Redirección inteligente según el rol
                    if ($user['id_rol'] == 1) {
                        header("Location: dashboard.php");
                    } else {
                        header("Location: registroGastos.php");
                    }
                    exit();
                }
            } else {
                $error = "❌ Usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            $error = "❌ Error de conexión. Intente más tarde.";
            error_log("Login error: " . $e->getMessage());
        }
    } else {
        $error = "❌ Por favor complete todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Gastos</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
        }
        .login-box h2 {
            color: #1e293b;
            margin-bottom: 10px;
            font-size: 28px;
            font-weight: 700;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: 500;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Outfit', sans-serif;
            transition: all 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: #0f172a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Outfit', sans-serif;
        }
        .btn:hover {
            background: #1e293b;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.3);
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #ef4444;
        }
        .role-info {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 13px;
            color: #475569;
        }
        .role-info strong { color: #1e293b; }
    </style>
</head>
<body>

    <div class="login-box">
        <h2>🔐 Iniciar Sesión</h2>
        <p class="subtitle">Ingresa tus credenciales para acceder al sistema</p>
        
        <?php if($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Usuario</label>
                <input type="text" name="usuario" placeholder="Ingresa tu usuario" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" placeholder="Ingresa tu contraseña" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn">→ Ingresar</button>

             <a href="recovery.php">Recuperar Contraseña</a>
        </form>
        
        <div class="role-info">
            <strong>📋 Roles disponibles:</strong><br>
            • <strong>Admin (id_rol = 1)</strong>: Acceso completo al sistema<br>
            • <strong>Usuario (id_rol = 2)</strong>: Solo acceso a Registro de Gastos
        </div>
    </div>

</body>
</html>