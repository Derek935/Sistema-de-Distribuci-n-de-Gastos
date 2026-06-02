<?php
// exportar_por_rubros.php
require_once 'config/auth.php';
requireAdmin();
require 'conexion/conexion.php';
require __DIR__ . '/vendor/autoload.php';

use Shuchkin\SimpleXLSXGen;

// Verificar que se hayan enviado los rubros
if (!isset($_POST['rubros']) || empty($_POST['rubros'])) {
    // Si no hay rubros seleccionados, exportar todos
    $stmt = $pdo->query("SELECT id_rubro FROM rubro WHERE activo = 1");
    $rubros_seleccionados = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $rubros_seleccionados = $_POST['rubros'];
}

// Obtener nombres de los rubros seleccionados
$placeholders = implode(',', array_fill(0, count($rubros_seleccionados), '?'));
$sql = "SELECT id_rubro, nombre_rubro FROM rubro WHERE id_rubro IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute($rubros_seleccionados);
$rubros_info = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener gastos de los rubros seleccionados
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
        COALESCE(m.nombre, t.nombre) as empleado,
        CASE 
            WHEN m.id_mantenedor IS NOT NULL THEN 'Mantenedor'
            WHEN t.id_tecnico IS NOT NULL THEN 'Técnico'
            ELSE 'N/A'
        END as tipo_empleado,
        g.fecha_registro
    FROM gasto g
    LEFT JOIN rubro r ON g.id_rubro = r.id_rubro
    LEFT JOIN programa p ON g.id_programa = p.id_programa
    LEFT JOIN periodo per ON g.id_periodo = per.id_periodo
    LEFT JOIN localidad l ON g.id_localidad = l.id_localidad
    LEFT JOIN mantenedor m ON g.id_mantenedor = m.id_mantenedor
    LEFT JOIN tecnico t ON g.id_tecnico = t.id_tecnico
    WHERE g.estado = 1 AND g.id_rubro IN ($placeholders)
    ORDER BY r.nombre_rubro, g.fecha_gasto DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($rubros_seleccionados);
$gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// GENERAR EXCEL
// ============================================

// Preparar datos
$rows = [];

// Título principal
$rows[] = ['REPORTE DE GASTOS POR RUBRO'];

// Rubros seleccionados
$rubros_nombres = array_column($rubros_info, 'nombre_rubro');
$rows[] = ['Rubros: ' . implode(', ', $rubros_nombres)];

// Fecha de generación
$rows[] = ['Fecha de generación: ' . date('d/m/Y H:i:s')];

// Fila vacía
$rows[] = [''];

// Encabezados de columna
$rows[] = [
    'Rubro',
    'ID Gasto',
    'Fecha',
    'Empleado',
    'Tipo',
    'Monto',
    'Programa',
    'Localidad',
    'Período',
    'Descripción'
];

// Datos
$total_por_rubro = [];
$total_general = 0;

foreach ($gastos as $gasto) {
    $rows[] = [
        $gasto['nombre_rubro'] ?? 'N/A',
        $gasto['id_gasto'],
        $gasto['fecha_gasto'],
        $gasto['empleado'] ?? 'N/A',
        $gasto['tipo_empleado'],
        $gasto['monto'],
        $gasto['programa'] ?? 'N/A',
        $gasto['nombre_localidad'] ?? 'N/A',
        $gasto['periodo'] ?? 'N/A',
        $gasto['descripcion'] ?? ''
    ];
    
    // Acumular totales por rubro
    $rubro_nombre = $gasto['nombre_rubro'] ?? 'N/A';
    if (!isset($total_por_rubro[$rubro_nombre])) {
        $total_por_rubro[$rubro_nombre] = 0;
    }
    $total_por_rubro[$rubro_nombre] += $gasto['monto'];
    $total_general += $gasto['monto'];
}

// Fila vacía
$rows[] = [''];

// Totales por rubro
$rows[] = ['RESUMEN POR RUBRO', '', '', '', '', '', '', '', '', ''];
foreach ($total_por_rubro as $rubro => $total) {
    $rows[] = [$rubro, '', '', '', '', $total, '', '', '', ''];
}

// Total general
$rows[] = ['', '', '', '', '', '', '', '', '', ''];
$rows[] = ['TOTAL GENERAL:', '', '', '', '', $total_general, '', '', '', ''];

// Crear Excel
try {
    $xlsx = SimpleXLSXGen::fromArray($rows);
    
    // Nombre del archivo
    $filename = 'Gastos_por_Rubros_' . date('Y-m-d') . '.xlsx';
    
    // Descargar
    $xlsx->downloadAs($filename);
    exit();
    
} catch (Exception $e) {
    die("❌ Error al generar Excel: " . $e->getMessage());
}
?>