<?php
session_start();
require 'conexion/conexion.php';
require 'vendor/autoload.php';


use Shuchkin\SimpleXLSXGen;

if (!isset($_SESSION['user_id']) || !isset($_POST['exportar'])) {
    header("Location: dashboard.php");
    exit();
}

$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');

// ===== CONSULTA =====
$sql = "SELECT 
    g.id_gasto,
    g.fecha_gasto,
    g.monto,
    g.descripcion,
    g.fecha_registro,
    p.programa,
    r.nombre_rubro,
    m.nombre as mantenedor,
    t.nombre as tecnico,
    l.nombre_localidad,
    CONCAT('Entre ', DATE_FORMAT(pe.fecha_inicio, '%d/%m/%Y'), ' y ', DATE_FORMAT(pe.fecha_fin, '%d/%m/%Y')) as periodo
FROM gasto g
LEFT JOIN programa p ON g.id_programa = p.id_programa
LEFT JOIN rubro r ON g.id_rubro = r.id_rubro
LEFT JOIN mantenedor m ON g.id_mantenedor = m.id_mantenedor
LEFT JOIN tecnico t ON g.id_tecnico = t.id_tecnico
LEFT JOIN periodo pe ON g.id_periodo = pe.id_periodo
LEFT JOIN localidad l ON pe.id_localidad = l.id_localidad
WHERE g.estado = 1 
AND DATE(g.fecha_registro) BETWEEN ? AND ?
ORDER BY g.fecha_registro DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$fecha_inicio, $fecha_fin]);
$gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== PREPARAR DATOS PARA EXCEL =====
$total = 0;
$filas = [];

// Título
$filas[] = ['REPORTE DE GASTOS POR FECHA DE INGRESO'];
$filas[] = ["Período: {$fecha_inicio} al {$fecha_fin}"];
$filas[] = ['', '', '', '', '', '', '', '', '', '', ''];

// Encabezados
$filas[] = ['ID', 'Fecha Gasto', 'Monto', 'Descripción', 'Fecha Registro', 
            'Programa', 'Rubro', 'Mantenedor', 'Técnico', 'Localidad', 'Período'];

// Datos
foreach ($gastos as $gasto) {
    $filas[] = [
        $gasto['id_gasto'],
        $gasto['fecha_gasto'],
        (float)$gasto['monto'],
        $gasto['descripcion'],
        $gasto['fecha_registro'],
        $gasto['programa'] ?? 'N/A',
        $gasto['nombre_rubro'] ?? 'N/A',
        $gasto['mantenedor'] ?? 'N/A',
        $gasto['tecnico'] ?? 'N/A',
        $gasto['nombre_localidad'] ?? 'N/A',
        $gasto['periodo'] ?? 'N/A'
    ];
    $total += $gasto['monto'];
}

// Fila de total
$filas[] = ['', '', '', '', '', '', '', '', '', '', ''];
$filas[] = ['TOTAL DEL PERÍODO:', '', $total, '', '', '', '', '', '', '', ''];

// ===== CREAR EXCEL =====
$xls = new SimpleXLSXGen();

// Hoja 1: Gastos por Fecha
$xls->addSheet($filas);

// Hoja 2: Resumen por Rubro
$sqlResumen = "SELECT 
    r.nombre_rubro,
    COUNT(*) as cantidad,
    SUM(g.monto) as total
FROM gasto g
LEFT JOIN rubro r ON g.id_rubro = r.id_rubro
WHERE g.estado = 1 
AND DATE(g.fecha_registro) BETWEEN ? AND ?
GROUP BY r.id_rubro, r.nombre_rubro
ORDER BY total DESC";

$stmtResumen = $pdo->prepare($sqlResumen);
$stmtResumen->execute([$fecha_inicio, $fecha_fin]);
$resumenRubro = $stmtResumen->fetchAll(PDO::FETCH_ASSOC);

$resumen = [['Rubro', 'Cantidad', 'Total']];
foreach ($resumenRubro as $row) {
    $resumen[] = [$row['nombre_rubro'] ?? 'Sin Rubro', $row['cantidad'], (float)$row['total']];
}
$xls->addSheet($resumen);

// Descargar
$filename = "Gastos_{$fecha_inicio}_al_{$fecha_fin}.xlsx";
$xls->downloadAs($filename);
exit();
?>