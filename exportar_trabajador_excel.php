<?php
// exportar_trabajador_excel.php
require 'conexion/conexion.php';

require 'vendor/autoload.php';
require_once 'config/auth.php';


// ✅ Usar autoload de Composer
requireAdmin();

use Shuchkin\SimpleXLSXGen;

// Verificar que se hayan enviado los datos
if (!isset($_POST['trabajador_id']) || !isset($_POST['tipo_trabajador'])) {
    die("❌ Error: No se especificó el trabajador a exportar");
}

$trabajador_id = intval($_POST['trabajador_id']);
$tipo_trabajador = $_POST['tipo_trabajador'];
$id_periodo_filtro = isset($_POST['periodo']) && $_POST['periodo'] !== '' ? intval($_POST['periodo']) : null;

// Obtener información del trabajador
if ($tipo_trabajador === 'tecnico') {
    $stmt = $pdo->prepare("SELECT nombre FROM tecnico WHERE id_tecnico = ?");
    $stmt->execute([$trabajador_id]);
    $trabajador = $stmt->fetch(PDO::FETCH_ASSOC);
    $campo_id = 'g.id_tecnico';
} else {
    $stmt = $pdo->prepare("SELECT nombre FROM mantenedor WHERE id_mantenedor = ?");
    $stmt->execute([$trabajador_id]);
    $trabajador = $stmt->fetch(PDO::FETCH_ASSOC);
    $campo_id = 'g.id_mantenedor';
}

if (!$trabajador) {
    die("❌ Error: Trabajador no encontrado");
}

// Obtener gastos del trabajador
$sql = "
    SELECT 
        g.id_gasto,
        g.fecha_gasto,
        g.monto,
        g.descripcion,
        r.nombre_rubro,
        p.programa,
        CONCAT('Entre ', DATE_FORMAT(per.fecha_inicio, '%d/%m/%Y'), ' y ', DATE_FORMAT(per.fecha_fin, '%d/%m/%Y')) as periodo,
        l.nombre_localidad,
        g.fecha_registro
    FROM gasto g
    LEFT JOIN rubro r ON g.id_rubro = r.id_rubro
    LEFT JOIN programa p ON g.id_programa = p.id_programa
    LEFT JOIN periodo per ON g.id_periodo = per.id_periodo
    LEFT JOIN localidad l ON g.id_localidad = l.id_localidad
    WHERE g.estado = 1 AND $campo_id = ?
";

$params = [$trabajador_id];

if ($id_periodo_filtro) {
    $sql .= " AND g.id_periodo = ?";
    $params[] = $id_periodo_filtro;
}

$sql .= " ORDER BY g.fecha_gasto DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// GENERAR EXCEL CON SimpleXLSXGen
// ============================================

// Preparar datos
$rows = [];

// Fila 1: Título principal
$rows[] = ['REPORTE DE GASTOS'];

// Fila 2: Nombre del trabajador
$rows[] = ['Trabajador: ' . $trabajador['nombre']];

// Fila 3: Período (si aplica)
if ($id_periodo_filtro) {
    $stmt = $pdo->prepare("SELECT CONCAT('Entre ', DATE_FORMAT(fecha_inicio, '%d/%m/%Y'), ' y ', DATE_FORMAT(fecha_fin, '%d/%m/%Y')) as periodo FROM periodo WHERE id_periodo = ?");
    $stmt->execute([$id_periodo_filtro]);
    $periodo_filtro = $stmt->fetchColumn();
    $rows[] = ['Período: ' . $periodo_filtro];
} else {
    $rows[] = [''];
}

// Fila vacía
$rows[] = [''];

// Encabezados de columna
$rows[] = [
    'ID',
    'Fecha',
    'Rubro',
    'Monto',
    'Programa',
    'Localidad',
    'Período',
    'Descripción',
    'Fecha Registro'
];

// Datos
$total = 0;
foreach ($gastos as $gasto) {
    $rows[] = [
        $gasto['id_gasto'],
        $gasto['fecha_gasto'],
        $gasto['nombre_rubro'] ?? 'N/A',
        $gasto['monto'],
        $gasto['programa'] ?? 'N/A',
        $gasto['nombre_localidad'] ?? 'N/A',
        $gasto['periodo'] ?? 'N/A',
        $gasto['descripcion'] ?? '',
        $gasto['fecha_registro']
    ];
    $total += $gasto['monto'];
}

// Fila de total
$rows[] = [
    '', '', 'TOTAL:', $total, '', '', '', '', ''
];

// Crear Excel
try {
    $xlsx = SimpleXLSXGen::fromArray($rows);
    
    // Nombre del archivo
    $filename = 'Gastos_' . preg_replace('/[^A-Za-z0-9]/', '_', $trabajador['nombre']) . '_' . date('Y-m-d') . '.xlsx';
    
    // Descargar
    $xlsx->downloadAs($filename);
    exit();
    
} catch (Exception $e) {
    die("❌ Error al generar Excel: " . $e->getMessage());
}
?>