<?php
// api/get_tecnicos.php
header('Content-Type: application/json');
require '../conexion/conexion.php';

$id_cuadrilla = $_GET['id_cuadrilla'] ?? 0;

if ($id_cuadrilla > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id_tecnico, nombre FROM tecnico WHERE id_cuadrilla = ? AND activo = 1 ORDER BY nombre ASC");
        $stmt->execute([$id_cuadrilla]);
        $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $tecnicos]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    $stmt = $pdo->query("SELECT id_tecnico, nombre FROM tecnico WHERE activo = 1 ORDER BY nombre ASC");
    $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $tecnicos]);
}
?>