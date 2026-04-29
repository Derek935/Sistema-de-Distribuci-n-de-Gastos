<?php
session_start();
require 'conexion/conexion.php';
require 'vendor/autoload.php';

use Shuchkin\SimpleXLSXGen;

if (!isset($_SESSION['user_id']) || !isset($_POST['exportar'])) {
    header("Location: dashboard.php");
    exit();
}

$id_periodo = $_POST['id_periodo'] ?? '';

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
WHERE g.estado = 1";

$params = [];
if (!empty($id_periodo)) {
    $sql .= " AND g.id_periodo = ?";
    $params[] = $id_periodo;
}
$sql .= " ORDER BY g.fecha_gasto DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== PREPARAR DATOS PARA EXCEL =====
$total = 0;
$filas = [];

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

// Fila vacía y total
$filas[] = ['', '', '', '', '', '', '', '', '', '', ''];
$filas[] = ['TOTAL GENERAL:', '', $total, '', '', '', '', '', '', '', ''];

// ===== CREAR EXCEL =====
$xls = new SimpleXLSXGen();

// Hoja 1: Gastos por Período
$xls->addSheet($filas);

// Hoja 2: Resumen
$resumen = [
    ['Concepto', 'Valor'],
    ['Total de Registros', count($gastos)],
    ['Monto Total', $total],
    ['Fecha de Exportación', date('d/m/Y H:i:s')],
    ['Período Filtrado', !empty($id_periodo) ? 'Sí' : 'Todos']
];
$xls->addSheet($resumen);

// Descargar
$filename = 'Gastos_Periodo_' . date('Y-m-d_His') . '.xlsx';
$xls->downloadAs($filename);
exit();
?>