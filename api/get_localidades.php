<?php
// api/get_localidades.php
header('Content-Type: application/json');
require '../conexion/conexion.php';

$id_programa = $_GET['id_programa'] ?? 0;

if ($id_programa > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id_localidad, nombre_localidad FROM localidad WHERE id_programa = ? AND estado = 1 ORDER BY nombre_localidad ASC");
        $stmt->execute([$id_programa]);
        $localidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $localidades]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    $stmt = $pdo->query("SELECT id_localidad, nombre_localidad FROM localidad WHERE estado = 1 ORDER BY nombre_localidad ASC");
    $localidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $localidades]);
}
?>