<?php
// api/get_tecnicos.php
header('Content-Type: application/json');
require '../conexion/conexion.php';

$id_grupo = $_GET['id_grupo'] ?? 0;

if ($id_grupo > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id_cuadrilla, nombre_cuadrilla FROM cuadrilla WHERE id_grupo = ? AND estado = 1 ORDER BY nombre ASC");
        $stmt->execute([$id_grupo]);
        $cuadrillas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $cuadrillas]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    $stmt = $pdo->query("SELECT id_cuadrilla, nombre_cuadrilla FROM cuadrilla WHERE estado = 1 ORDER BY nombre ASC");
    $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $cuadrillas]);
}
?>