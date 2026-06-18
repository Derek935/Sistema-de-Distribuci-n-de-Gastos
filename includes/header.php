<?php
// includes/header.php
require_once __DIR__ . '/../config/auth.php';

// 🔥 DETECTAR RUTA BASE AUTOMÁTICAMENTE
$scriptName = $_SERVER['SCRIPT_NAME'];
$folderPath = dirname($scriptName);

// Si estás en subcarpeta, usar esa ruta. Si no, usar raíz
$basePath = ($folderPath === '/' || $folderPath === '\\') ? '' : $folderPath;

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
    
    <!-- ✅ CSS CON RUTA DINÁMICA -->
</head>
<body>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Outfit', sans-serif; background: #f5f5f5; }
    .header { background: #0f172a; color: white; padding: 1rem 2rem; }
    .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
    
    /* ✅ CAMBIO: Logo con flexbox para alinear el badge */
    .logo { 
        font-size: 1.5rem; 
        font-weight: 700; 
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .navbar { display: flex; gap: 1rem; align-items: center; }
    .nav-item { color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 4px; transition: all 0.2s; }
    .nav-item:hover { background: #1e293b; }
    .nav-item.active { background: #3b82f6; }
    .btn-logout { background: #ef4444; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; }
    main { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
</style>
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
                    <a href="<?php echo htmlspecialchars($basePath . '/' . $item['url']); ?>" 
                    class="nav-item <?php echo trim($class); ?>">
                        <?php echo htmlspecialchars($item['label']); ?>
                    </a>
                <?php endforeach; ?>
                
                <a href="<?php echo $basePath; ?>/logout.php" class="btn-logout">Salir</a>
            <?php else: ?>
                <a href="<?php echo $basePath; ?>/index.php" class="nav-item">Iniciar Sesión</a>
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