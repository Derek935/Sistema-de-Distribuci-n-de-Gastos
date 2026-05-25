<?php
// api/get_tecnicos.php
header('Content-Type: application/json');
require '../conexion/conexion.php';

$id_zona = $_GET['id_zona'] ?? 0;

if ($id_zona > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id_tecnico, nombre FROM tecnico WHERE id_cuadrilla = ? AND activo = 1 ORDER BY nombre ASC");
        $stmt->execute([$id_zona]);
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