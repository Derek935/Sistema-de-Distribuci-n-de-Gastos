<?php
// config/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verificar si el usuario está logueado
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Verificar si el usuario es Admin (id_rol = 1)
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 1;
}

/**
 * Obtener datos del usuario actual
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'nombre' => $_SESSION['username'] ?? null,
        'id_rol' => $_SESSION['user_rol'] ?? null,
        'email' => $_SESSION['user_email'] ?? null
    ];
}

/**
 * Redirigir si no es admin - SIN RUTAS ABSOLUTAS
 */
function requireAdmin() {
    // Evitar bucles de redirección
    $currentScript = basename($_SERVER['PHP_SELF']);
    
    if (!isLoggedIn()) {
        // Guardar URL actual para redirigir después del login
        $_SESSION['redirect_after_login'] = $currentScript;
        header("Location: index.php");
        exit();
    }
    
    if (!isAdmin()) {
        // Si no es admin, redirigir a registroGastos.php
        $_SESSION['error'] = "Acceso denegado. Solo administradores pueden acceder.";
        header("Location: registroGastos.php");
        exit();
    }
}

/**
 * Verificar si puede acceder a una vista
 */
function canAccess($vista) {
    if (!isLoggedIn()) return false;
    
    // Admin tiene acceso a todo
    if (isAdmin()) {
        return true;
    }
    
    // Usuarios (rol 2) solo pueden ver estas vistas
    $vistasPermitidas = ['registroGastos.php', 'index.php', 'logout.php'];
    return in_array($vista, $vistasPermitidas);
}

/**
 * Obtener menú según rol
 */
function getMenuItems() {
    $user = getCurrentUser();
    if (!$user) return [];
    
    $menu = [];
    
    // Para todos los usuarios
    $menu[] = [
        'label' => '📝 Registro de Gastos',
        'url' => 'registroGastos.php',
        'roles' => [1, 2]
    ];
    
    // ✅ NUEVO: Panel de Control - Solo para Admin (rol 1)
    if ($user['id_rol'] === 1) {
        $menu[] = [
            'label' => '📤 Comprobantes y Rubros',
            'url' => 'panel.php',
            'roles' => [1],
            'class' => 'admin-only'
        ];
    }
    
    // Solo para Admin
    if ($user['id_rol'] === 1) {
        $menu[] = [
            'label' => '📊 Dashboard',
            'url' => 'dashboard.php',
            'roles' => [1],
            'class' => 'admin-only'
        ];
    }
    
    return $menu;
}

/**
 * Badge de rol
 */
function getRoleBadge() {
    $user = getCurrentUser();
    if (!$user) return '';
    
    if ($user['id_rol'] === 1) {
        return '<span class="role-badge admin">
                    <span class="role-icon">👑</span>
                    <span>Admin</span>
                </span>';
    }
    return '<span class="role-badge user">
                <span class="role-icon">👤</span>
                <span>Usuario</span>
            </span>';
}