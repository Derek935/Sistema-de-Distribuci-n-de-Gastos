<?php
// api/get_mantenedores.php
header('Content-Type: application/json');
require '../conexion/conexion.php';

$id_cuadrilla = $_GET['id_cuadrilla'] ?? 0;

if ($id_cuadrilla > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id_mantenedor, nombre FROM mantenedor WHERE id_cuadrilla = ? AND estado = 1 ORDER BY nombre ASC");
        $stmt->execute([$id_cuadrilla]);
        $mantenedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $mantenedores]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    // Si no hay grupo seleccionado, traer todos o vacíos
    $stmt = $pdo->query("SELECT id_mantenedor, nombre FROM mantenedor WHERE estado = 1 ORDER BY nombre ASC");
    $mantenedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $mantenedores]);
}
?>