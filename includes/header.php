<?php
// includes/header.php
require_once __DIR__ . '/../config/auth.php';

// Verificar sesión solo si no es index.php o login
$currentFile = basename($_SERVER['PHP_SELF']);
if (!isLoggedIn() && !in_array($currentFile, ['index.php'])) {
    header("Location: index.php");
    exit();
}

$user = getCurrentUser();
$menuItems = getMenuItems();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gastos</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; }
        
        .header {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 12px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .role-badge.admin { background: #ef4444; }
        .role-badge.user { background: #22c55e; }
        
        .navbar {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .nav-item {
            color: white;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .nav-item:hover { background: rgba(255,255,255,0.15); }
        .nav-item.active { background: rgba(99, 102, 241, 0.3); }
        .nav-item.admin-only { border: 1px solid #ef4444; }
        
        .btn-logout {
            background: #ef4444;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
        }
        
        main { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 20px;
            margin: 15px 0;
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-content">
        <div class="logo">
            📊 Sistema de Gastos
            <?php if($user) echo getRoleBadge(); ?>
        </div>
        
        <nav class="navbar">
            <?php if($user): ?>
                <?php foreach($menuItems as $item): ?>
                    <?php 
                    $isActive = (strpos($_SERVER['REQUEST_URI'], $item['url']) !== false);
                    $class = $isActive ? 'active' : '';
                    if (isset($item['class'])) $class .= ' ' . $item['class'];
                    ?>
                    <a href="<?php echo htmlspecialchars($item['url']); ?>" 
                       class="nav-item <?php echo trim($class); ?>">
                        <?php echo htmlspecialchars($item['label']); ?>
                    </a>
                <?php endforeach; ?>
                
                <a href="logout.php" class="btn-logout">🚪 Salir</a>
            <?php else: ?>
                <a href="index.php" class="nav-item">🔐 Iniciar Sesión</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php if(isset($_SESSION['error'])): ?>
    <div class="alert-error">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<main>