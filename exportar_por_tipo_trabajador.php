<?php
// exportar_por_tipo_trabajador.php
require_once 'config/auth.php';
requireAdmin();
require 'conexion/conexion.php';
require __DIR__ . '/vendor/autoload.php';

use Shuchkin\SimpleXLSXGen;

// Verificar que se haya seleccionado el tipo
if (!isset($_POST['tipo_trabajador']) || empty($_POST['tipo_trabajador'])) {
    die("❌ Error: No se especificó el tipo de trabajador a exportar");
}

$tipo_trabajador = $_POST['tipo_trabajador'];
$id_periodo_filtro = isset($_POST['id_periodo']) && $_POST['id_periodo'] !== '' ? intval($_POST['id_periodo']) : null;

// Determinar el campo y título según el tipo
if ($tipo_trabajador === 'todos_mantenedores') {
    $campo_id = 'g.id_mantenedor';
    $campo_nombre = 'm.nombre';
    $titulo_reporte = 'TODOS LOS MANTENEDORES';
    $condicion_tipo = "m.id_mantenedor IS NOT NULL";
} elseif ($tipo_trabajador === 'todos_tecnicos') {
    $campo_id = 'g.id_tecnico';
    $campo_nombre = 't.nombre';
    $titulo_reporte = 'TODOS LOS TÉCNICOS';
    $condicion_tipo = "t.id_tecnico IS NOT NULL";
} else {
    die("❌ Error: Tipo de trabajador no válido");
}

// Obtener gastos
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
        $campo_nombre as empleado,
        g.fecha_registro
    FROM gasto g
    LEFT JOIN rubro r ON g.id_rubro = r.id_rubro
    LEFT JOIN programa p ON g.id_programa = p.id_programa
    LEFT JOIN periodo per ON g.id_periodo = per.id_periodo
    LEFT JOIN localidad l ON g.id_localidad = l.id_localidad
    LEFT JOIN mantenedor m ON g.id_mantenedor = m.id_mantenedor
    LEFT JOIN tecnico t ON g.id_tecnico = t.id_tecnico
    WHERE g.estado = 1 AND $condicion_tipo
";

$params = [];

if ($id_periodo_filtro) {
    $sql .= " AND g.id_periodo = ?";
    $params[] = $id_periodo_filtro;
}

$sql .= " ORDER BY empleado, g.fecha_gasto DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// GENERAR EXCEL
// ============================================

// Preparar datos
$rows = [];

// Título principal
$rows[] = ['REPORTE DE GASTOS'];
$rows[] = ['Tipo: ' . $titulo_reporte];

// Período si aplica
if ($id_periodo_filtro) {
    $stmt = $pdo->prepare("SELECT CONCAT('Entre ', DATE_FORMAT(fecha_inicio, '%d/%m/%Y'), ' y ', DATE_FORMAT(fecha_fin, '%d/%m/%Y')) as periodo FROM periodo WHERE id_periodo = ?");
    $stmt->execute([$id_periodo_filtro]);
    $periodo_filtro = $stmt->fetchColumn();
    $rows[] = ['Período: ' . $periodo_filtro];
}

// Fecha de generación
$rows[] = ['Fecha de generación: ' . date('d/m/Y H:i:s')];

// Fila vacía
$rows[] = [''];

// Encabezados de columna
$rows[] = [
    'Empleado',
    'ID Gasto',
    'Fecha',
    'Rubro',
    'Monto',
    'Programa',
    'Localidad',
    'Período',
    'Descripción'
];

// Datos
$total_por_empleado = [];
$total_general = 0;
$empleado_actual = null;

foreach ($gastos as $gasto) {
    $rows[] = [
        $gasto['empleado'] ?? 'N/A',
        $gasto['id_gasto'],
        $gasto['fecha_gasto'],
        $gasto['nombre_rubro'] ?? 'N/A',
        $gasto['monto'],
        $gasto['programa'] ?? 'N/A',
        $gasto['nombre_localidad'] ?? 'N/A',
        $gasto['periodo'] ?? 'N/A',
        $gasto['descripcion'] ?? ''
    ];
    
    // Acumular totales por empleado
    $empleado = $gasto['empleado'] ?? 'N/A';
    if (!isset($total_por_empleado[$empleado])) {
        $total_por_empleado[$empleado] = 0;
    }
    $total_por_empleado[$empleado] += $gasto['monto'];
    $total_general += $gasto['monto'];
}

// Fila vacía
$rows[] = [''];

// Totales por empleado
$rows[] = ['RESUMEN POR EMPLEADO', '', '', '', '', '', '', '', ''];
foreach ($total_por_empleado as $empleado => $total) {
    $rows[] = [$empleado, '', '', '', $total, '', '', '', ''];
}

// Total general
$rows[] = ['', '', '', '', '', '', '', '', ''];
$rows[] = ['TOTAL GENERAL:', '', '', '', $total_general, '', '', '', ''];

// Crear Excel
try {
    $xlsx = SimpleXLSXGen::fromArray($rows);
    
    // Nombre del archivo
    $nombre_tipo = ($tipo_trabajador === 'todos_mantenedores') ? 'Mantenedores' : 'Tecnicos';
    $filename = 'Gastos_' . $nombre_tipo . '_' . date('Y-m-d') . '.xlsx';
    
    // Descargar
    $xlsx->downloadAs($filename);
    exit();
    
} catch (Exception $e) {
    die("❌ Error al generar Excel: " . $e->getMessage());
}
?>