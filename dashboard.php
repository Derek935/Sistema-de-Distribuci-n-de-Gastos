<?php
// dashboard.php
require 'config/auth.php';

// ✅ Proteger vista - Solo admin
requireAdmin();

require 'conexion/conexion.php';
// Si llega aquí, es admin
require 'includes/header.php';

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

// 🔍 Cargar todos los trabajadores (mantenedores y técnicos)
$trabajadores = $pdo->query("
    SELECT id_mantenedor as id, nombre, 'Mantenedor' as tipo FROM mantenedor WHERE estado = 1
    UNION ALL
    SELECT id_tecnico as id, nombre, 'Técnico' as tipo FROM tecnico WHERE activo = 1
    ORDER BY tipo, nombre
")->fetchAll(PDO::FETCH_ASSOC);

// 🔍 BUSCAR GASTOS POR TRABAJADOR
$gastos_trabajador = [];
$trabajador_seleccionado = null;
$total_gastos = 0;

if (isset($_GET['buscar_trabajador']) && !empty($_GET['trabajador_id'])) {
    $trabajador_id = intval($_GET['trabajador_id']);
    $tipo_trabajador = $_GET['tipo_trabajador'] ?? 'mantenedor';
    
    // Obtener información del trabajador
    if ($tipo_trabajador === 'tecnico') {
        $stmt = $pdo->prepare("SELECT nombre FROM tecnico WHERE id_tecnico = ?");
        $stmt->execute([$trabajador_id]);
        $trabajador_seleccionado = $stmt->fetch(PDO::FETCH_ASSOC);
        $tipo = 'Técnico';
    } else {
        $stmt = $pdo->prepare("SELECT nombre FROM mantenedor WHERE id_mantenedor = ?");
        $stmt->execute([$trabajador_id]);
        $trabajador_seleccionado = $stmt->fetch(PDO::FETCH_ASSOC);
        $tipo = 'Mantenedor';
    }
    
    if ($trabajador_seleccionado) {
        // Obtener gastos del trabajador con sus comprobantes
        $sql = "
            SELECT 
                g.id_gasto,
                g.fecha_gasto,
                g.monto,
                g.descripcion,
                g.comprobante,
                g.fecha_registro,
                r.nombre_rubro,
                p.programa
            FROM gasto g
            LEFT JOIN rubro r ON g.id_rubro = r.id_rubro
            LEFT JOIN programa p ON g.id_programa = p.id_programa
            LEFT JOIN periodo per ON g.id_periodo = per.id_periodo
            WHERE g.estado = 1 AND ";
        
        if ($tipo_trabajador === 'tecnico') {
            $sql .= "g.id_tecnico = ?";
        } else {
            $sql .= "g.id_mantenedor = ?";
        }
        
        $sql .= " ORDER BY g.fecha_gasto DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$trabajador_id]);
        $gastos_trabajador = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular total
        $total_gastos = array_sum(array_column($gastos_trabajador, 'monto'));
    }
}
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
</head>
<body>

    <!-- Contenedor principal con clase dashboard- -->
    <div class="dashboard-container">
        
        <!-- Estadísticas Generales -->
        <div class="dashboard-stats-grid">
            <div class="dashboard-stat-card blue">
                <span class="icon">💰</span>
                <h3>Total Gastado</h3>
                <div class="number">$<?php echo number_format($stats_generales['monto_total'], 2); ?></div>
            </div>
            <div class="dashboard-stat-card green">
                <span class="icon">📋</span>
                <h3>Total de Gastos</h3>
                <div class="number"><?php echo $stats_generales['total_gastos']; ?></div>
            </div>
            <div class="dashboard-stat-card orange">
                <span class="icon">📈</span>
                <h3>Promedio por Gasto</h3>
                <div class="number">$<?php echo number_format($stats_generales['promedio_gasto'], 2); ?></div>
            </div>
            <div class="dashboard-stat-card purple">
                <span class="icon">🏢</span>
                <h3>Localidades</h3>
                <div class="number"><?php echo count($gastos_por_localidad); ?></div>
            </div>
        </div>

        <!-- Gráficas en Mosaico (2 columnas) -->
        <div class="dashboard-charts-grid">
            
            <!-- Fila 1, Columna 1: Gastos por Período -->
            <div class="dashboard-chart-card">
                <h3>📅 Gastos por Período</h3>
                <div class="dashboard-chart-container">
                    <div id="chartPeriodo"></div>
                </div>
            </div>

            <!-- Fila 1, Columna 2: Gastos por Rubro -->
            <div class="dashboard-chart-card">
                <h3>📊 Gastos por Rubro</h3>
                <div class="dashboard-chart-container">
                    <div id="chartRubro"></div>
                </div>
            </div>

            <!-- Fila 2, Columna 1: Gastos por Programa -->
            <div class="dashboard-chart-card">
                <h3>🎯 Gastos por Programa</h3>
                <div class="dashboard-chart-container">
                    <div id="chartPrograma"></div>
                </div>
            </div>

            <!-- Fila 2, Columna 2: Evolución Mensual -->
            <div class="dashboard-chart-card">
                <h3>📈 Evolución Mensual Gastos</h3>
                <div class="dashboard-chart-container">
                    <div id="chartMensual"></div>
                </div>
            </div>
        </div>

        <!-- Tabla de Top Empleados -->
        <div class="dashboard-table-card">
            <h3>Empleados con Más Gastos</h3>
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
        <div class="dashboard-table-card">
            <h3>Gastos por Localidad</h3>
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

        <!--  SECCIÓN DE BÚSQUEDA POR TRABAJADOR -->
        <div class="dashboard-search-section">
            <h3>
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Buscar Gastos por Trabajador
            </h3>
            
            <form method="GET" class="dashboard-search-form">
                <div class="form-group">
                    <label>Tipo de Trabajador</label>
                    <select name="tipo_trabajador" id="tipo_trabajador" required>
                        <option value="mantenedor">Mantenedor</option>
                        <option value="tecnico">Técnico</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Seleccionar Trabajador</label>
                    <select name="trabajador_id" id="trabajador_id" required>
                        <option value="">-- Seleccione --</option>
                        <?php foreach($trabajadores as $trab): ?>
                            <option value="<?php echo $trab['id']; ?>" 
                                    data-tipo="<?php echo strtolower($trab['tipo']); ?>">
                                <?php echo htmlspecialchars($trab['nombre']); ?> (<?php echo $trab['tipo']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" name="buscar_trabajador" class="dashboard-btn-search">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Buscar
                    </button>
                </div>
            </form>
        </div>

        <!-- RESULTADOS DE BÚSQUEDA -->
        <?php if ($trabajador_seleccionado): ?>
            <div class="dashboard-results-container">
                <div class="dashboard-results-header">
                    <h3 class="dashboard-results-title">
                        📋 Gastos de <?php echo htmlspecialchars($trabajador_seleccionado['nombre']); ?>
                    </h3>
                    <div class="dashboard-results-total">
                        Total: $<?php echo number_format($total_gastos, 2); ?> 
                        (<?php echo count($gastos_trabajador); ?> gastos)
                    </div>
                </div>
                
                <?php if (empty($gastos_trabajador)): ?>
                    <div class="dashboard-sin-resultados">
                        <div class="dashboard-sin-resultados-icon">📭</div>
                        <h3>No se encontraron gastos</h3>
                        <p>Este trabajador no tiene gastos registrados en el sistema.</p>
                    </div>
                <?php else: ?>
                    <div class="dashboard-gastos-grid">
                        <?php foreach($gastos_trabajador as $gasto): ?>
                            <div class="dashboard-gasto-card">
                                <div class="dashboard-gasto-header">
                                    <div>
                                        <div class="dashboard-gasto-fecha">
                                             <?php echo date('d/m/Y', strtotime($gasto['fecha_gasto'])); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                                            Registrado: <?php echo date('d/m/Y H:i', strtotime($gasto['fecha_registro'])); ?>
                                        </div>
                                    </div>
                                    <div class="dashboard-gasto-monto">
                                        $<?php echo number_format($gasto['monto'], 2); ?>
                                    </div>
                                </div>
                                
                                <div class="dashboard-gasto-info">
                                    <div class="dashboard-gasto-label">Rubro</div>
                                    <div class="dashboard-gasto-value"> <?php echo htmlspecialchars($gasto['nombre_rubro'] ?? 'N/A'); ?></div>
                                </div>
                                
                                <div class="dashboard-gasto-info">
                                    <div class="dashboard-gasto-label">Programa</div>
                                    <div class="dashboard-gasto-value"> <?php echo htmlspecialchars($gasto['programa'] ?? 'N/A'); ?></div>
                                </div>
                                
                                <div class="dashboard-gasto-info">
                                    <div class="dashboard-gasto-label">Período</div>
                                    <div class="dashboard-gasto-value"> <?php echo htmlspecialchars($gasto['periodo'] ?? 'N/A'); ?></div>
                                </div>
                                
                                <?php if ($gasto['descripcion']): ?>
                                    <div class="dashboard-gasto-info">
                                        <div class="dashboard-gasto-label">Descripción</div>
                                        <div class="dashboard-gasto-value"><?php echo htmlspecialchars($gasto['descripcion']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($gasto['comprobante']): ?>
                                    <div class="dashboard-gasto-comprobante">
                                        <div class="dashboard-gasto-label">📎 Comprobante</div>
                                        <?php 
                                        $ext = strtolower(pathinfo($gasto['comprobante'], PATHINFO_EXTENSION));
                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): 
                                        ?>
                                            <img src="<?php echo htmlspecialchars($gasto['comprobante']); ?>" 
                                                 alt="Comprobante" 
                                                 class="dashboard-comprobante-img"
                                                 onclick="window.open(this.src, '_blank')">
                                        <?php elseif ($ext === 'pdf'): ?>
                                            <a href="<?php echo htmlspecialchars($gasto['comprobante']); ?>" 
                                               target="_blank" 
                                               class="dashboard-comprobante-pdf">
                                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                                                </svg>
                                                Ver PDF
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="dashboard-gasto-comprobante">
                                        <div class="dashboard-sin-comprobante">Sin comprobante</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Botones de Exportación -->
        <div class="dashboard-export-buttons">
            <button onclick="openModal('modalPeriodos')" class="dashboard-btn-export dashboard-btn-primary">
                📅 Exportar por Período
            </button>
            <button onclick="openModal('modalFechas')" class="dashboard-btn-export dashboard-btn-success">
                📆 Exportar por Fecha de Ingreso
            </button>
        </div>

    </div>

    <!-- Modal: Exportar por Períodos -->
    <div id="modalPeriodos" class="dashboard-modal">
        <div class="dashboard-modal-content">
            <span class="dashboard-close" onclick="closeModal('modalPeriodos')">&times;</span>
            <h2>📅 Exportar Gastos por Período</h2>
            <form action="exportar_periodos.php" method="POST">
                <div class="form-group">
                    <label>Seleccionar Período:</label>
                    <select name="id_periodo" class="form-control">
                        <option value="">-- Todos los períodos --</option>
                        <?php 
                        $stmt = $pdo->query("SELECT id_periodo, CONCAT('Entre ', DATE_FORMAT(fecha_inicio, '%d/%m/%Y'), ' y ', DATE_FORMAT(fecha_fin, '%d/%m/%Y')) as periodo FROM periodo ORDER BY fecha_inicio DESC");
                        while($p = $stmt->fetch(PDO::FETCH_ASSOC)): 
                        ?>
                            <option value="<?php echo $p['id_periodo']; ?>">
                                <?php echo htmlspecialchars($p['periodo']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" name="exportar" class="btn dashboard-btn-primary btn-block">
                    📥 Descargar Excel (.xlsx)
                </button>
            </form>
        </div>
    </div>

    <!-- Modal: Exportar por Fecha -->
    <div id="modalFechas" class="dashboard-modal">
        <div class="dashboard-modal-content">
            <span class="dashboard-close" onclick="closeModal('modalFechas')">&times;</span>
            <h2>📆 Exportar Gastos por Fecha de Ingreso</h2>
            <form action="exportar_por_fecha.php" method="POST">
                <div class="form-group">
                    <label>Fecha de Inicio:</label>
                    <input type="date" name="fecha_inicio" required class="form-control" value="<?php echo date('Y-m-01'); ?>">
                </div>
                <div class="form-group">
                    <label>Fecha de Fin:</label>
                    <input type="date" name="fecha_fin" required class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <button type="submit" name="exportar" class="btn dashboard-btn-success btn-block">
                    📥 Descargar Excel (.xlsx)
                </button>
            </form>
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

// ===== FUNCIONES DE MODALES =====
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    if (event.target.classList.contains('dashboard-modal')) {
        event.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Cerrar modal con tecla ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.dashboard-modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>