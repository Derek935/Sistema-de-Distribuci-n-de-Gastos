<?php
session_start();
require 'conexion/conexion.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// ============================================
// 📊 CONSULTAS PARA EL DASHBOARD
// ============================================

// 1. Total general de gastos
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_gastos,
    COALESCE(SUM(monto), 0) as monto_total,
    COALESCE(AVG(monto), 0) as promedio_gasto
FROM gasto WHERE estado = 1");
$stats_generales = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Gastos por Período
$stmt = $pdo->query("SELECT 
    p.id_periodo,
    CONCAT('Entre ', DATE_FORMAT(p.fecha_inicio, '%d/%m/%Y'), ' y ', DATE_FORMAT(p.fecha_fin, '%d/%m/%Y')) as periodo,
    COUNT(g.id_gasto) as cantidad,
    COALESCE(SUM(g.monto), 0) as total
FROM periodo p
LEFT JOIN gasto g ON p.id_periodo = g.id_periodo AND g.estado = 1
GROUP BY p.id_periodo, periodo
ORDER BY p.fecha_inicio DESC");
$gastos_por_periodo = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Gastos por Rubro
$stmt = $pdo->query("SELECT 
    r.nombre_rubro,
    COUNT(g.id_gasto) as cantidad,
    COALESCE(SUM(g.monto), 0) as total
FROM rubro r
LEFT JOIN gasto g ON r.id_rubro = g.id_rubro AND g.estado = 1
GROUP BY r.id_rubro, r.nombre_rubro
ORDER BY total DESC");
$gastos_por_rubro = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Gastos por Programa
$stmt = $pdo->query("SELECT 
    pr.programa,
    COUNT(g.id_gasto) as cantidad,
    COALESCE(SUM(g.monto), 0) as total
FROM programa pr
LEFT JOIN gasto g ON pr.id_programa = g.id_programa AND g.estado = 1
GROUP BY pr.id_programa, pr.programa
ORDER BY total DESC");
$gastos_por_programa = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Gastos por Empleado (Mantenedor/Técnico)
$stmt = $pdo->query("SELECT 
    COALESCE(m.nombre, t.nombre, 'Sin asignar') as empleado,
    CASE 
        WHEN m.id_mantenedor IS NOT NULL THEN 'Mantenedor'
        WHEN t.id_tecnico IS NOT NULL THEN 'Técnico'
        ELSE 'Sin asignar'
    END as tipo,
    COUNT(g.id_gasto) as cantidad,
    COALESCE(SUM(g.monto), 0) as total
FROM gasto g
LEFT JOIN mantenedor m ON g.id_mantenedor = m.id_mantenedor
LEFT JOIN tecnico t ON g.id_tecnico = t.id_tecnico
WHERE g.estado = 1
GROUP BY empleado, tipo
ORDER BY total DESC
LIMIT 10");
$gastos_por_empleado = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Gastos por Mes (últimos 6 meses)
$stmt = $pdo->query("SELECT 
    DATE_FORMAT(fecha_gasto, '%Y-%m') as mes,
    DATE_FORMAT(fecha_gasto, '%M %Y') as mes_nombre,
    COUNT(*) as cantidad,
    SUM(monto) as total
FROM gasto 
WHERE estado = 1 AND fecha_gasto >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY mes, mes_nombre
ORDER BY mes ASC");
$gastos_por_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Gastos por Localidad
$stmt = $pdo->query("SELECT 
    l.nombre_localidad,
    COUNT(g.id_gasto) as cantidad,
    COALESCE(SUM(g.monto), 0) as total
FROM localidad l
INNER JOIN periodo p ON l.id_localidad = p.id_localidad
LEFT JOIN gasto g ON p.id_periodo = g.id_periodo AND g.estado = 1
GROUP BY l.id_localidad, l.nombre_localidad
ORDER BY total DESC");
$gastos_por_localidad = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar datos para gráficas (JSON)
$labels_periodo = array_column($gastos_por_periodo, 'periodo');
$data_periodo = array_column($gastos_por_periodo, 'total');

$labels_rubro = array_column($gastos_por_rubro, 'nombre_rubro');
$data_rubro = array_column($gastos_por_rubro, 'total');

$labels_programa = array_column($gastos_por_programa, 'programa');
$data_programa = array_column($gastos_por_programa, 'total');

$labels_mes = array_column($gastos_por_mes, 'mes_nombre');
$data_mes = array_column($gastos_por_mes, 'total');

$labels_localidad = array_column($gastos_por_localidad, 'nombre_localidad');
$data_localidad = array_column($gastos_por_localidad, 'total');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Gastos</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/styles.css" />
    <script src="js/vendor/apexcharts.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: #f8f9fa;
            padding-top: 80px; /* Espacio para el header fijo */
        }
        
        /* ===== HEADER FIJO ===== */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
        }
        
        .navbar {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .navbar span {
            opacity: 0.9;
        }
        
        .nav-item {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .nav-item:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .nav-item.active {
            background: rgba(255,255,255,0.3);
        }
        
        /* ===== CONTENEDOR PRINCIPAL ===== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        /* ===== GRID DE ESTADÍSTICAS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-card h3 {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .stat-card .icon {
            float: right;
            font-size: 40px;
            opacity: 0.2;
        }
        
        .stat-card.blue { border-left: 4px solid #3498db; }
        .stat-card.green { border-left: 4px solid #2ecc71; }
        .stat-card.orange { border-left: 4px solid #e67e22; }
        .stat-card.purple { border-left: 4px solid #9b59b6; }
        
        /* ===== GRID DE GRÁFICAS (MOSAICO) ===== */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* 2 columnas */
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .chart-card h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }
        
        /* ===== TABLAS ===== */
        .table-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .table-card h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        table th {
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }
        
        table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-primary { 
            background: #e3f2fd; 
            color: #1976d2; 
        }
        
        .badge-success { 
            background: #e8f5e9; 
            color: #388e3c; 
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr; /* 1 columna en tablets */
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 120px; /* Más espacio en móviles */
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .navbar {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr; /* 1 columna en móviles */
            }
            
            .chart-container {
                height: 300px;
            }
        }
    </style>
</head>
<body>

    <!-- Header Fijo -->
    <div class="header">
        <div class="header-content">
            <div class="logo">📊 Dashboard de Gastos</div>
            <div class="navbar">
                <span>👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="registroGastos.php" class="nav-item">Registrar Gasto</a>
                <a href="dashboard.php" class="nav-item active">Dashboard</a>
                <a href="logout.php" class="nav-item">Cerrar Sesión</a>
            </div>
        </div>
    </div>

    <div class="container">
        
        <!-- Estadísticas Generales -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <span class="icon">💰</span>
                <h3>Total Gastado</h3>
                <div class="number">$<?php echo number_format($stats_generales['monto_total'], 2); ?></div>
            </div>
            <div class="stat-card green">
                <span class="icon">📋</span>
                <h3>Total de Gastos</h3>
                <div class="number"><?php echo $stats_generales['total_gastos']; ?></div>
            </div>
            <div class="stat-card orange">
                <span class="icon">📈</span>
                <h3>Promedio por Gasto</h3>
                <div class="number">$<?php echo number_format($stats_generales['promedio_gasto'], 2); ?></div>
            </div>
            <div class="stat-card purple">
                <span class="icon">🏢</span>
                <h3>Localidades</h3>
                <div class="number"><?php echo count($gastos_por_localidad); ?></div>
            </div>
        </div>

        <!-- Gráficas en Mosaico (2 columnas) -->
        <div class="charts-grid">
            
            <!-- Fila 1, Columna 1: Gastos por Período -->
            <div class="chart-card">
                <h3>📅 Gastos por Período</h3>
                <div class="chart-container">
                    <div id="chartPeriodo"></div>
                </div>
            </div>

            <!-- Fila 1, Columna 2: Gastos por Rubro -->
            <div class="chart-card">
                <h3>📊 Gastos por Rubro</h3>
                <div class="chart-container">
                    <div id="chartRubro"></div>
                </div>
            </div>

            <!-- Fila 2, Columna 1: Gastos por Programa -->
            <div class="chart-card">
                <h3>🎯 Gastos por Programa</h3>
                <div class="chart-container">
                    <div id="chartPrograma"></div>
                </div>
            </div>

            <!-- Fila 2, Columna 2: Evolución Mensual -->
            <div class="chart-card">
                <h3>📈 Evolución Mensual</h3>
                <div class="chart-container">
                    <div id="chartMensual"></div>
                </div>
            </div>
        </div>

        <!-- Tabla de Top Empleados -->
        <div class="table-card">
            <h3>👥 Top 10 Empleados con Más Gastos</h3>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Empleado</th>
                        <th>Tipo</th>
                        <th>Cantidad de Gastos</th>
                        <th>Total Gastado</th>
                        <th>Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $contador = 1;
                    foreach($gastos_por_empleado as $emp): 
                        $promedio = $emp['cantidad'] > 0 ? $emp['total'] / $emp['cantidad'] : 0;
                    ?>
                    <tr>
                        <td><?php echo $contador++; ?></td>
                        <td><strong><?php echo htmlspecialchars($emp['empleado']); ?></strong></td>
                        <td>
                            <span class="badge <?php echo $emp['tipo'] === 'Mantenedor' ? 'badge-primary' : 'badge-success'; ?>">
                                <?php echo $emp['tipo']; ?>
                            </span>
                        </td>
                        <td><?php echo $emp['cantidad']; ?></td>
                        <td><strong>$<?php echo number_format($emp['total'], 2); ?></strong></td>
                        <td>$<?php echo number_format($promedio, 2); ?></td>
                    </tr>
                    <?php endforeach;?>
                </tbody>
            </table>
        </div>

        <!-- Gastos por Localidad -->
        <div class="table-card">
            <h3>📍 Gastos por Localidad</h3>
            <table>
                <thead>
                    <tr>
                        <th>Localidad</th>
                        <th>Cantidad de Gastos</th>
                        <th>Total</th>
                        <th>Porcentaje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_general = $stats_generales['monto_total'];
                    foreach($gastos_por_localidad as $loc): 
                        $porcentaje = $total_general > 0 ? ($loc['total'] / $total_general) * 100 : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($loc['nombre_localidad']); ?></strong></td>
                        <td><?php echo $loc['cantidad']; ?></td>
                        <td><strong>$<?php echo number_format($loc['total'], 2); ?></strong></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="flex: 1; background: #ecf0f1; height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div style="width: <?php echo $porcentaje; ?>%; background: #3498db; height: 100%;"></div>
                                </div>
                                <span style="font-size: 12px; color: #7f8c8d;"><?php echo number_format($porcentaje, 1); ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Scripts de ApexCharts -->
    <script>
document.addEventListener('DOMContentLoaded', function() {
    
    const colors = {
        blue: '#3498db',
        green: '#2ecc71',
        orange: '#e67e22',
        purple: '#9b59b6',
        red: '#e74c3c'
    };

    function crearGraficaApex(elementId, options) {
        const el = document.querySelector("#" + elementId);
        if (!el) {
            console.warn(`⚠️ Elemento #${elementId} no encontrado`);
            return null;
        }
        if (typeof ApexCharts === 'undefined') {
            console.error(`❌ ApexCharts no cargado para #${elementId}`);
            return null;
        }
        try {
            const chart = new ApexCharts(el, options);
            chart.render();
            return chart;
        } catch (e) {
            console.error(`❌ Error en #${elementId}:`, e.message);
            return null;
        }
    }

    // 1. GRÁFICA DE PERÍODOS
    crearGraficaApex('chartPeriodo', {
        chart: { type: 'bar', height: 350, toolbar: { show: false } },
        plotOptions: {
            bar: { horizontal: true, borderRadius: 4, barHeight: '70%' }
        },
        series: [{
            name: 'Total Gastado',
            data: <?php echo json_encode($data_periodo ?: [0], JSON_NUMERIC_CHECK); ?>
        }],
        xaxis: {
            categories: <?php echo json_encode($labels_periodo); ?>,
            labels: {
                formatter: function(value) {
                    return '$' + parseFloat(value || 0).toLocaleString();
                }
            }
        },
        colors: [colors.blue],
        legend: { show: false }
    });

    // 2. GRÁFICA DE RUBROS
    crearGraficaApex('chartRubro', {
        chart: { type: 'donut', height: 350 },
        series: <?php echo json_encode($data_rubro ?: [0], JSON_NUMERIC_CHECK); ?>,
        labels: <?php echo json_encode($labels_rubro); ?>,
        colors: [colors.blue, colors.green, colors.orange, colors.purple, colors.red],
        legend: { position: 'bottom', show: true },
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total',
                            formatter: function(w) {
                                return '$' + (w.globals.seriesTotals.reduce((a,b)=>a+b,0) || 0).toLocaleString();
                            }
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        tooltip: {
            y: { formatter: function(v) { return '$' + (v||0).toLocaleString(); } }
        }
    });

    // 3. GRÁFICA DE PROGRAMAS
    crearGraficaApex('chartPrograma', {
        chart: { type: 'radialBar', height: 350 },
        series: <?php echo json_encode($data_programa ?: [0], JSON_NUMERIC_CHECK); ?>,
        labels: <?php echo json_encode($labels_programa); ?>,
        colors: [colors.blue, colors.green, colors.orange, colors.purple, colors.red],
        plotOptions: {
            radialBar: {
                dataLabels: {
                    name: { fontSize: '14px', fontWeight: 600 },
                    value: {
                        fontSize: '14px',
                        formatter: function(val) { return '$' + (val||0).toLocaleString(); }
                    },
                    total: {
                        show: true,
                        label: 'Total',
                        formatter: function(w) {
                            return '$' + (w.globals.seriesTotals.reduce((a,b)=>a+b,0) || 0).toLocaleString();
                        }
                    }
                }
            }
        },
        stroke: { lineCap: 'round' }
    });

    // 4. GRÁFICA DE EVOLUCIÓN MENSUAL
    crearGraficaApex('chartMensual', {
        chart: {
            type: 'area', height: 350, toolbar: { show: false },
            zoom: { enabled: true },
            animations: { enabled: true, easing: 'easeinout', speed: 800 }
        },
        series: [{
            name: 'Gastos Mensuales',
            data: <?php echo json_encode($data_mes ?: [0], JSON_NUMERIC_CHECK); ?>
        }],
        xaxis: {
            categories: <?php echo json_encode($labels_mes); ?>,
            tooltip: { enabled: false }
        },
        yaxis: {
            labels: {
                formatter: function(value) {
                    return '$' + parseFloat(value || 0).toLocaleString();
                }
            }
        },
        colors: [colors.green],
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.3, stops: [0, 90, 100] }
        },
        stroke: { curve: 'smooth', width: 3 },
        markers: {
            size: 5, colors: ['#fff'], strokeColors: colors.green, strokeWidth: 2, hover: { size: 7 }
        },
        tooltip: {
            y: { formatter: function(v) { return '$' + (v||0).toLocaleString(); } }
        }
    });

    console.log('✅ ApexCharts inicializado');
});
</script>

</body>
</html>