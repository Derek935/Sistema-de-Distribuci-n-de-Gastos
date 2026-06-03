<?php
// dashboard.php
require 'config/auth.php';

// ✅ Proteger vista - Solo admin
requireAdmin();

require 'conexion/conexion.php';
require_once 'includes/header.php';

// ============================================
// 🎯 FILTRO POR PERÍODO
// ============================================
$id_periodo_filtro = isset($_GET['periodo']) && $_GET['periodo'] !== '' ? intval($_GET['periodo']) : null;

// ============================================
// 📊 CONSULTAS PARA EL DASHBOARD
// ============================================

// 1. Total general de gastos
$sql_stats = "SELECT 
    COUNT(*) as total_gastos,
    COALESCE(SUM(monto), 0) as monto_total,
    COALESCE(AVG(monto), 0) as promedio_gasto
FROM gasto WHERE estado = 1";
$params_stats = [];

if ($id_periodo_filtro) {
    $sql_stats .= " AND id_periodo = ?";
    $params_stats[] = $id_periodo_filtro;
}

$stmt = $pdo->prepare($sql_stats);
$stmt->execute($params_stats);
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
$sql_rubro = "SELECT 
    r.nombre_rubro,
    COUNT(g.id_gasto) as cantidad,
    COALESCE(SUM(g.monto), 0) as total
FROM rubro r
LEFT JOIN gasto g ON r.id_rubro = g.id_rubro AND g.estado = 1";
$params_rubro = [];

if ($id_periodo_filtro) {
    $sql_rubro .= " WHERE g.id_periodo = ?";
    $params_rubro[] = $id_periodo_filtro;
}
$sql_rubro .= " GROUP BY r.id_rubro, r.nombre_rubro ORDER BY total DESC";

$stmt = $pdo->prepare($sql_rubro);
$stmt->execute($params_rubro);
$gastos_por_rubro = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Gastos por Programa
$sql_programa = "SELECT 
    pr.programa,
    COUNT(g.id_gasto) as cantidad,
    COALESCE(SUM(g.monto), 0) as total
FROM programa pr
LEFT JOIN gasto g ON pr.id_programa = g.id_programa AND g.estado = 1";
$params_programa = [];

if ($id_periodo_filtro) {
    $sql_programa .= " WHERE g.id_periodo = ?";
    $params_programa[] = $id_periodo_filtro;
}
$sql_programa .= " GROUP BY pr.id_programa, pr.programa ORDER BY total DESC";

$stmt = $pdo->prepare($sql_programa);
$stmt->execute($params_programa);
$gastos_por_programa = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Gastos por Empleado
$sql_empleado = "SELECT 
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
WHERE g.estado = 1";
$params_empleado = [];

if ($id_periodo_filtro) {
    $sql_empleado .= " AND g.id_periodo = ?";
    $params_empleado[] = $id_periodo_filtro;
}
$sql_empleado .= " GROUP BY empleado, tipo ORDER BY total DESC LIMIT 10";

$stmt = $pdo->prepare($sql_empleado);
$stmt->execute($params_empleado);
$gastos_por_empleado = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Gastos por Mes
$sql_mes = "SELECT 
    DATE_FORMAT(fecha_gasto, '%Y-%m') as mes,
    DATE_FORMAT(fecha_gasto, '%M %Y') as mes_nombre,
    COUNT(*) as cantidad,
    SUM(monto) as total
FROM gasto 
WHERE estado = 1";
$params_mes = [];

if ($id_periodo_filtro) {
    $sql_mes .= " AND id_periodo = ?";
    $params_mes[] = $id_periodo_filtro;
}
$sql_mes .= " AND fecha_gasto >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY mes, mes_nombre
ORDER BY mes ASC";

$stmt = $pdo->prepare($sql_mes);
$stmt->execute($params_mes);
$gastos_por_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar datos para gráficas
$labels_periodo = array_column($gastos_por_periodo, 'periodo');
$data_periodo = array_column($gastos_por_periodo, 'total');

$labels_rubro = array_column($gastos_por_rubro, 'nombre_rubro');
$data_rubro = array_column($gastos_por_rubro, 'total');

$labels_programa = array_column($gastos_por_programa, 'programa');
$data_programa = array_column($gastos_por_programa, 'total');

$labels_mes = array_column($gastos_por_mes, 'mes_nombre');
$data_mes = array_column($gastos_por_mes, 'total');

// 🔍 Cargar trabajadores
$trabajadores = $pdo->query("
    SELECT id_mantenedor as id, nombre, 'Mantenedor' as tipo FROM mantenedor WHERE estado = 1
    UNION ALL
    SELECT id_tecnico as id, nombre, 'Técnico' as tipo FROM tecnico WHERE activo = 1
    ORDER BY tipo, nombre
")->fetchAll(PDO::FETCH_ASSOC);

// 🔍 Cargar rubros
$rubros_lista = $pdo->query("SELECT id_rubro, nombre_rubro FROM rubro WHERE activo = 1 ORDER BY nombre_rubro ASC")->fetchAll(PDO::FETCH_ASSOC);

// 🔍 BUSCAR GASTOS POR TRABAJADOR
$gastos_trabajador = [];
$trabajador_seleccionado = null;
$total_gastos = 0;

if (isset($_GET['buscar_trabajador']) && !empty($_GET['trabajador_id'])) {
    $trabajador_id = intval($_GET['trabajador_id']);
    $tipo_trabajador = $_GET['tipo_trabajador'] ?? 'mantenedor';
    
    if ($tipo_trabajador === 'tecnico') {
        $stmt = $pdo->prepare("SELECT nombre FROM tecnico WHERE id_tecnico = ?");
        $stmt->execute([$trabajador_id]);
        $trabajador_seleccionado = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT nombre FROM mantenedor WHERE id_mantenedor = ?");
        $stmt->execute([$trabajador_id]);
        $trabajador_seleccionado = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
   if ($trabajador_seleccionado) {
    // ✅ CAMBIO: Incluir estado 1 Y 2, y traer el campo estado
    $sql = "
        SELECT 
            g.id_gasto, g.fecha_gasto, g.monto, g.descripcion, g.comprobante, g.fecha_registro, g.estado,
            r.nombre_rubro, p.programa,
            CONCAT('Entre ', DATE_FORMAT(per.fecha_inicio, '%d/%m/%Y'), ' y ', DATE_FORMAT(per.fecha_fin, '%d/%m/%Y')) as periodo
        FROM gasto g
        LEFT JOIN rubro r ON g.id_rubro = r.id_rubro
        LEFT JOIN programa p ON g.id_programa = p.id_programa
        LEFT JOIN periodo per ON g.id_periodo = per.id_periodo
        WHERE g.estado IN (1, 2) AND ";
    
    $sql .= ($tipo_trabajador === 'tecnico') ? "g.id_tecnico = ?" : "g.id_mantenedor = ?";
    $params_buscar = [$trabajador_id];
    
    if ($id_periodo_filtro) {
        $sql .= " AND g.id_periodo = ?";
        $params_buscar[] = $id_periodo_filtro;
    }
    
    $sql .= " ORDER BY g.fecha_gasto DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_buscar);
    $gastos_trabajador = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_gastos = array_sum(array_column($gastos_trabajador, 'monto'));
}
}

// 🔍 Cargar períodos
$periodos_disponibles = $pdo->query("
    SELECT id_periodo, 
           CONCAT('Entre ', DATE_FORMAT(fecha_inicio, '%d/%m/%Y'), ' y ', DATE_FORMAT(fecha_fin, '%d/%m/%Y')) as periodo
    FROM periodo 
    ORDER BY fecha_inicio DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Gastos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/styles.css" />
    <script src="js/vendor/apexcharts.min.js"></script>

</head>
<body>

<div class="dashboard-container">
    
    <!-- 🔹 FILTRO DE PERÍODO -->
    <div class="periodo-filtro">
        <label for="filtro_periodo">📅 Filtrar por período:</label>
        <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <select name="periodo" id="filtro_periodo" onchange="this.form.submit()">
                <option value="">-- Todos los períodos --</option>
                <?php foreach($periodos_disponibles as $p): ?>
                    <option value="<?= $p['id_periodo'] ?>" <?= $id_periodo_filtro == $p['id_periodo'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['periodo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($id_periodo_filtro): ?>
                <span class="filtro-activo-badge">
                    ✅ Filtro activo: 
                    <?php 
                    $p_activo = array_filter($periodos_disponibles, fn($p) => $p['id_periodo'] == $id_periodo_filtro);
                    echo htmlspecialchars(reset($p_activo)['periodo'] ?? '');
                    ?>
                </span>
                <button type="button" class="btn-clear" onclick="window.location.href='dashboard.php'">
                    ✕ Limpiar filtro
                </button>
            <?php endif; ?>
        </form>
    </div>

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
            <span class="icon">👥</span>
            <h3>Empleados Activos</h3>
            <div class="number"><?php echo count($gastos_por_empleado); ?></div>
        </div>
    </div>

    <!-- Gráficas en Mosaico -->
    <div class="dashboard-charts-grid">
        <div class="dashboard-chart-card">
            <h3>📅 Gastos por Período</h3>
            <div class="dashboard-chart-container">
                <div id="chartPeriodo"></div>
            </div>
        </div>
        <div class="dashboard-chart-card">
            <h3>📊 Gastos por Rubro</h3>
            <div class="dashboard-chart-container">
                <div id="chartRubro"></div>
            </div>
        </div>
        <div class="dashboard-chart-card">
            <h3>🎯 Gastos por Programa</h3>
            <div class="dashboard-chart-container">
                <div id="chartPrograma"></div>
            </div>
        </div>
        <div class="dashboard-chart-card">
            <h3>📈 Evolución Mensual Gastos</h3>
            <div class="dashboard-chart-container">
                <div id="chartMensual"></div>
            </div>
        </div>
    </div>

    <!-- Tabla de Top Empleados -->
    <div class="dashboard-table-card">
        <h3>Empleados con Más Gastos <?php echo $id_periodo_filtro ? '(Período seleccionado)' : ''; ?></h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Empleado</th>
                    <th>Tipo</th>
                    <th>Cantidad</th>
                    <th>Total</th>
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
                    <td><span class="badge <?php echo $emp['tipo'] === 'Mantenedor' ? 'badge-primary' : 'badge-success'; ?>"><?php echo $emp['tipo']; ?></span></td>
                    <td><?php echo $emp['cantidad']; ?></td>
                    <td><strong>$<?php echo number_format($emp['total'], 2); ?></strong></td>
                    <td>$<?php echo number_format($promedio, 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($gastos_por_empleado)): ?>
                <tr><td colspan="6" style="text-align:center; padding:20px; color:#95a5a6;">No hay gastos en este período</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- SECCIÓN DE BÚSQUEDA POR TRABAJADOR -->
    <div class="dashboard-search-section">
        <h3>🔍 Buscar Gastos por Trabajador</h3>
        <form method="GET" class="dashboard-search-form">
            <?php if ($id_periodo_filtro): ?>
                <input type="hidden" name="periodo" value="<?= $id_periodo_filtro ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Tipo</label>
                <select name="tipo_trabajador" id="tipo_trabajador" required>
                    <option value="mantenedor">Mantenedor</option>
                    <option value="tecnico">Técnico</option>
                </select>
            </div>
            <div class="form-group">
                <label>Trabajador</label>
                <select name="trabajador_id" id="trabajador_id" required>
                    <option value="">-- Seleccione --</option>
                    <?php foreach($trabajadores as $trab): ?>
                        <option value="<?= $trab['id'] ?>" data-tipo="<?= strtolower($trab['tipo']) ?>"
                            <?= (isset($_GET['trabajador_id']) && $_GET['trabajador_id'] == $trab['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($trab['nombre']) ?> (<?= $trab['tipo'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" name="buscar_trabajador" class="dashboard-btn-search">🔍 Buscar</button>
            </div>
        </form>
    </div>

    <!-- RESULTADOS DE BÚSQUEDA -->
    <?php if ($trabajador_seleccionado): ?>
        <div class="dashboard-results-container">
            <div class="dashboard-results-header">
                <div>
                    <h3 class="dashboard-results-title">📋 Gastos de <?= htmlspecialchars($trabajador_seleccionado['nombre']) ?></h3>
                    <?php if ($id_periodo_filtro): ?>
                        <small style="color: #64748b;">Filtro: Período #<?= $id_periodo_filtro ?></small>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <div class="dashboard-results-total">
                        Total: $<?= number_format($total_gastos, 2) ?> (<?= count($gastos_trabajador) ?> gastos)
                    </div>
                    <!-- ✅ BOTÓN DESCARGAR EXCEL DEL TRABAJADOR -->
                    <form method="POST" action="exportar_trabajador_excel.php" target="_blank" style="display: inline;">
                        <input type="hidden" name="trabajador_id" value="<?= $trabajador_id ?>">
                        <input type="hidden" name="tipo_trabajador" value="<?= $tipo_trabajador ?>">
                        <?php if ($id_periodo_filtro): ?>
                            <input type="hidden" name="periodo" value="<?= $id_periodo_filtro ?>">
                        <?php endif; ?>
                        <button type="submit" class="dashboard-btn-export dashboard-btn-primary" style="padding: 8px 16px; font-size: 13px;">
                            📥 Descargar Excel
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if (empty($gastos_trabajador)): ?>
                <div class="dashboard-sin-resultados">
                    <div class="dashboard-sin-resultados-icon">📭</div>
                    <h3>No se encontraron gastos</h3>
                    <p>Este trabajador no tiene gastos registrados <?php echo $id_periodo_filtro ? 'en el período seleccionado' : ''; ?>.</p>
                </div>
            <?php else: ?>
                <div class="dashboard-gastos-grid">
                    <?php foreach($gastos_trabajador as $gasto): ?>
                        <div class="dashboard-gasto-card">
                            <div class="dashboard-gasto-header">
                                <div>
                                    <div class="dashboard-gasto-fecha"><?= date('d/m/Y', strtotime($gasto['fecha_gasto'])) ?></div>
                                    <div style="font-size:12px; color:#9ca3af; margin-top:4px;">
                                        Registrado: <?= date('d/m/Y H:i', strtotime($gasto['fecha_registro'])) ?>
                                    </div>
                                    <!-- ✅ NUEVO: Badge de estado -->
                                    <div style="margin-top: 6px;">
                                        <?php if ($gasto['estado'] == 1): ?>
                                            <span class="badge-estado badge-pendiente">⏳ Pendiente</span>
                                        <?php else: ?>
                                            <span class="badge-estado badge-finalizado">✅ Finalizado</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="dashboard-gasto-monto">$<?= number_format($gasto['monto'], 2) ?></div>
                            </div>
                            <div class="dashboard-gasto-info">
                                <div class="dashboard-gasto-label">Rubro</div>
                                <div class="dashboard-gasto-value"><?= htmlspecialchars($gasto['nombre_rubro'] ?? 'N/A') ?></div>
                            </div>
                            <div class="dashboard-gasto-info">
                                <div class="dashboard-gasto-label">Programa</div>
                                <div class="dashboard-gasto-value"><?= htmlspecialchars($gasto['programa'] ?? 'N/A') ?></div>
                            </div>
                            <div class="dashboard-gasto-info">
                                <div class="dashboard-gasto-label">Período</div>
                                <div class="dashboard-gasto-value"><?= htmlspecialchars($gasto['periodo'] ?? 'N/A') ?></div>
                            </div>
                            <?php if ($gasto['descripcion']): ?>
                                <div class="dashboard-gasto-info">
                                    <div class="dashboard-gasto-label">Descripción</div>
                                    <div class="dashboard-gasto-value"><?= htmlspecialchars($gasto['descripcion']) ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($gasto['comprobante']): ?>
                                <div class="dashboard-gasto-comprobante">
                                    <div class="dashboard-gasto-label">📎 Comprobante</div>
                                    <?php 
                                    $ext = strtolower(pathinfo($gasto['comprobante'], PATHINFO_EXTENSION));
                                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): 
                                    ?>
                                        <!-- ✅ IMAGEN CON TAMAÑO LIMITADO -->
                                        <img src="<?= htmlspecialchars($gasto['comprobante']) ?>" 
                                            alt="Comprobante" 
                                            class="dashboard-comprobante-img" 
                                            onclick="window.open(this.src, '_blank')"
                                            title="Clic para ver en tamaño completo">
                                    <?php elseif ($ext === 'pdf'): ?>
                                        <a href="<?= htmlspecialchars($gasto['comprobante']) ?>" target="_blank" class="dashboard-comprobante-pdf">📄 Ver PDF</a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="dashboard-gasto-comprobante"><div class="dashboard-sin-comprobante">Sin comprobante</div></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Botones de Exportación -->
    <div class="dashboard-export-buttons">
        <button onclick="openModal('modalPeriodos')" class="dashboard-btn-export dashboard-btn-primary">📅 Exportar por Período</button>
        <button onclick="openModal('modalFechas')" class="dashboard-btn-export dashboard-btn-success">📆 Exportar por Fecha</button>
        <button onclick="openModal('modalRubros')" class="dashboard-btn-export dashboard-btn-primary">📊 Exportar por Rubros</button>
        <button onclick="openModal('modalTipoTrabajador')" class="dashboard-btn-export dashboard-btn-success">👥 Exportar por Tipo</button>
    </div>

</div>

<!-- Modales -->
<div id="modalPeriodos" class="dashboard-modal">
    <div class="dashboard-modal-content">
        <span class="dashboard-close" onclick="closeModal('modalPeriodos')">&times;</span>
        <h2>📅 Exportar Gastos por Período</h2>
        <form action="exportar_periodos.php" method="POST">
            <div class="form-group">
                <label>Seleccionar Período:</label>
                <select name="id_periodo" class="form-control">
                    <option value="">-- Todos los períodos --</option>
                    <?php foreach($periodos_disponibles as $p): ?>
                        <option value="<?= $p['id_periodo'] ?>"><?= htmlspecialchars($p['periodo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="exportar" class="btn dashboard-btn-primary btn-block">📥 Descargar Excel</button>
        </form>
    </div>
</div>

<div id="modalFechas" class="dashboard-modal">
    <div class="dashboard-modal-content">
        <span class="dashboard-close" onclick="closeModal('modalFechas')">&times;</span>
        <h2>📆 Exportar Gastos por Fecha</h2>
        <form action="exportar_por_fecha.php" method="POST">
            <div class="form-group">
                <label>Fecha Inicio:</label>
                <input type="date" name="fecha_inicio" required class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="form-group">
                <label>Fecha Fin:</label>
                <input type="date" name="fecha_fin" required class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <button type="submit" name="exportar" class="btn dashboard-btn-success btn-block">📥 Descargar Excel</button>
        </form>
    </div>
</div>

<!-- ✅ NUEVO MODAL: EXPORTAR POR RUBROS -->
<div id="modalRubros" class="dashboard-modal">
    <div class="dashboard-modal-content">
        <span class="dashboard-close" onclick="closeModal('modalRubros')">&times;</span>
        <h2>📊 Exportar Gastos por Rubros</h2>
        <form action="exportar_por_rubros.php" method="POST">
            <div class="form-group">
                <label>Seleccionar Rubros:</label>
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; margin-top: 8px;">
                    <?php foreach($rubros_lista as $rubro): ?>
                        <label style="display: flex; align-items: center; gap: 8px; padding: 8px; margin-bottom: 4px; cursor: pointer; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                            <input type="checkbox" name="rubros[]" value="<?= $rubro['id_rubro'] ?>" style="width: 18px; height: 18px; cursor: pointer;">
                            <span><?= htmlspecialchars($rubro['nombre_rubro']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <small style="color: #64748b; display: block; margin-top: 8px;">💡 Deja todos desmarcados para exportar todos los rubros</small>
            </div>
            <button type="submit" name="exportar" class="btn dashboard-btn-primary btn-block">📥 Descargar Excel</button>
        </form>
    </div>
</div>

<!-- ✅ NUEVO MODAL: EXPORTAR POR TIPO DE TRABAJADOR -->
<div id="modalTipoTrabajador" class="dashboard-modal">
    <div class="dashboard-modal-content">
        <span class="dashboard-close" onclick="closeModal('modalTipoTrabajador')">&times;</span>
        <h2>👥 Exportar por Tipo de Trabajador</h2>
        <form action="exportar_por_tipo_trabajador.php" method="POST">
            <div class="form-group">
                <label>Tipo de Trabajador:</label>
                <select name="tipo_trabajador" class="form-control" required>
                    <option value="">-- Seleccione --</option>
                    <option value="todos_mantenedores">👷 Todos los Mantenedores</option>
                    <option value="todos_tecnicos">🔧 Todos los Técnicos</option>
                </select>
            </div>
            <div class="form-group">
                <label>Filtrar por período (opcional):</label>
                <select name="id_periodo" class="form-control">
                    <option value="">-- Todos los períodos --</option>
                    <?php foreach($periodos_disponibles as $p): ?>
                        <option value="<?= $p['id_periodo'] ?>"><?= htmlspecialchars($p['periodo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="exportar" class="btn dashboard-btn-success btn-block">📥 Descargar Excel</button>
        </form>
    </div>
</div>

<!-- Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const colors = { blue:'#3498db', green:'#2ecc71', orange:'#e67e22', purple:'#9b59b6', red:'#e74c3c' };

    function crearGraficaApex(elementId, options) {
        const el = document.querySelector("#" + elementId);
        if (!el || typeof ApexCharts === 'undefined') return null;
        try {
            const chart = new ApexCharts(el, options);
            chart.render();
            return chart;
        } catch(e) { console.error(`❌ Error en #${elementId}:`, e.message); return null; }
    }

    // 1. GRÁFICA DE PERÍODOS
    crearGraficaApex('chartPeriodo', {
        chart: { type: 'bar', height: 350, toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '70%' } },
        series: [{ name: 'Total Gastado', data: <?= json_encode($data_periodo ?: [0], JSON_NUMERIC_CHECK) ?> }],
        xaxis: {
            categories: <?= json_encode($labels_periodo) ?>,
            labels: { formatter: v => '$' + parseFloat(v||0).toLocaleString() }
        },
        colors: [colors.blue],
        legend: { show: false }
    });

    // 2. GRÁFICA DE RUBROS
    crearGraficaApex('chartRubro', {
        chart: { type: 'donut', height: 350 },
        series: <?= json_encode($data_rubro ?: [0], JSON_NUMERIC_CHECK) ?>,
        labels: <?= json_encode($labels_rubro) ?>,
        colors: [colors.blue, colors.green, colors.orange, colors.purple, colors.red],
        legend: { position: 'bottom' },
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: {
                        show: true,
                        total: {
                            show: true, label: 'Total',
                            formatter: w => '$' + (w.globals.seriesTotals.reduce((a,b)=>a+b,0)||0).toLocaleString()
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: v => '$' + (v||0).toLocaleString() } }
    });

    // 3. GRÁFICA DE PROGRAMAS
    crearGraficaApex('chartPrograma', {
        chart: { type: 'radialBar', height: 350 },
        series: <?= json_encode($data_programa ?: [0], JSON_NUMERIC_CHECK) ?>,
        labels: <?= json_encode($labels_programa) ?>,
        colors: [colors.blue, colors.green, colors.orange, colors.purple, colors.red],
        plotOptions: {
            radialBar: {
                dataLabels: {
                    name: { fontSize: '14px', fontWeight: 600 },
                    value: { fontSize: '14px', formatter: v => '$' + (v||0).toLocaleString() },
                    total: {
                        show: true, label: 'Total',
                        formatter: w => '$' + (w.globals.seriesTotals.reduce((a,b)=>a+b,0)||0).toLocaleString()
                    }
                }
            }
        },
        stroke: { lineCap: 'round' }
    });

    // 4. GRÁFICA DE EVOLUCIÓN MENSUAL
    crearGraficaApex('chartMensual', {
        chart: { type: 'area', height: 350, toolbar: { show: false }, zoom: { enabled: true } },
        series: [{ name: 'Gastos Mensuales', data: <?= json_encode($data_mes ?: [0], JSON_NUMERIC_CHECK) ?> }],
        xaxis: { categories: <?= json_encode($labels_mes) ?>, tooltip: { enabled: false } },
        yaxis: { labels: { formatter: v => '$' + parseFloat(v||0).toLocaleString() } },
        colors: [colors.green],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.3, stops: [0, 90, 100] } },
        stroke: { curve: 'smooth', width: 3 },
        markers: { size: 5, colors: ['#fff'], strokeColors: colors.green, strokeWidth: 2, hover: { size: 7 } },
        tooltip: { y: { formatter: v => '$' + (v||0).toLocaleString() } }
    });

    console.log('✅ ApexCharts inicializado');
});

// Modales
function openModal(id) { document.getElementById(id).style.display = 'block'; document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; document.body.style.overflow = 'auto'; }
window.onclick = e => { if(e.target.classList.contains('dashboard-modal')) { e.target.style.display='none'; document.body.style.overflow='auto'; } };
document.addEventListener('keydown', e => { if(e.key==='Escape') { document.querySelectorAll('.dashboard-modal').forEach(m => { if(m.style.display==='block') { m.style.display='none'; document.body.style.overflow='auto'; } }); } });
</script>

<?php require_once 'includes/footer.php'; ?>