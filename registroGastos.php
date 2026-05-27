<?php
require_once 'config/auth.php';

if (!isLoggedIn()) {
    header("Location: /index.php");
    exit();
}

require 'conexion/conexion.php';
require_once 'includes/header.php';

// Configuración de upload
$uploadDir = 'uploads/comprobantes/';
$maxFileSize = 5 * 1024 * 1024;
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ✅ Cargar datos iniciales
$zonas = $pdo->query("SELECT id_zona, nombre_zona FROM zona WHERE estado = 1 ORDER BY nombre_zona ASC")->fetchAll(PDO::FETCH_ASSOC);
$localidades = $pdo->query("SELECT id_localidad, nombre_localidad, id_zona FROM localidad WHERE estado = 1 ORDER BY nombre_localidad ASC")->fetchAll(PDO::FETCH_ASSOC);
$programas = $pdo->query("SELECT id_programa, programa FROM programa ORDER BY programa ASC")->fetchAll(PDO::FETCH_ASSOC);
$rubros = $pdo->query("SELECT id_rubro, nombre_rubro FROM rubro WHERE activo = 1 ORDER BY nombre_rubro ASC")->fetchAll(PDO::FETCH_ASSOC);
$periodo = $pdo->query("SELECT id_periodo, concat('Entre ',fecha_inicio,' y ',fecha_fin) periodo FROM periodo WHERE estado = 'EN PROCESO' ORDER BY periodo ASC")->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// PROCESAR FORMULARIO DE PERIODO
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btnperiodo'])) {
    try {
        $feini = $_POST['feini'] ?? '';
        $fefin = $_POST['fefin'] ?? '';
        $id_zona = !empty($_POST['zona']) ? intval($_POST['zona']) : 0;

        $stmtMax = $pdo->query("SELECT MAX(id_periodo+1) as max_id FROM periodo");
        $resultado = $stmtMax->fetch(PDO::FETCH_ASSOC);
        $id_periodo = $resultado['max_id'];
        
        if (empty($feini)) throw new Exception('La fecha de inicio es obligatoria');
        if (empty($fefin)) throw new Exception('La fecha de término es obligatoria');
        if ($id_zona <= 0) throw new Exception('La zona es obligatoria');
        if ($feini > $fefin) throw new Exception('La fecha de inicio no puede ser mayor a la fecha de término');
        if (empty($id_periodo)) throw new Exception('No se pudo obtener el ID del período');
        
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO periodo (id_periodo, fecha_inicio, fecha_fin, id_zona, estado) VALUES (?, ?, ?, ?, 'EN PROCESO')");
        $stmt->execute([$id_periodo, $feini, $fefin, $id_zona]);
        $pdo->commit();
        
        $_SESSION['mensaje'] = "✅ Periodo registrado correctamente";
        $_SESSION['tipo_mensaje'] = 'success';
        header("Location: registroGastos.php");
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['mensaje'] = "❌ Error BD: " . ($e->errorInfo[2] ?? $e->getMessage());
        $_SESSION['tipo_mensaje'] = 'error';
        header("Location: registroGastos.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['mensaje'] = "❌ Error: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = 'error';
        header("Location: registroGastos.php");
        exit();
    }
}

// ============================================
// PROCESAR FORMULARIO DE GASTO - MANTENEDOR
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btngasto_mantenedor'])) {
    try {
        $fecha_gasto = $_POST['fecha_gasto'] ?? date('Y-m-d');
        $id_mantenedor = !empty($_POST['Mantenedor']) ? intval($_POST['Mantenedor']) : null;
        $id_periodo = !empty($_POST['Periodo']) ? intval($_POST['Periodo']) : null;
        $id_programa = !empty($_POST['Programa']) ? intval($_POST['Programa']) : null;
        
        $rubros = $_POST['rubro'] ?? [];
        $montos = $_POST['monto_gasto'] ?? [];
        $observaciones = $_POST['observaciones'] ?? [];
        
        $ruta_comprobante = null;
        if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['comprobante'];
            if ($archivo['size'] > $maxFileSize) throw new Exception('El archivo excede 5MB');
            
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($archivo['tmp_name']);
            if (!in_array($mimeType, $allowedTypes)) throw new Exception('Tipo de archivo no permitido');
            
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) throw new Exception('Extensión no permitida');
            
            $nombreUnico = uniqid('comp_') . '_' . time() . '.' . $extension;
            $rutaDestino = $uploadDir . $nombreUnico;
            if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) throw new Exception('Error al guardar');
            $ruta_comprobante = $rutaDestino;
        }

        if (empty($fecha_gasto)) throw new Exception('La fecha es obligatoria');
        if (!$id_mantenedor) throw new Exception('Debe seleccionar un mantenedor');
        if (!$id_periodo) throw new Exception('Debe seleccionar un periodo');
        if (!$id_programa) throw new Exception('Debe seleccionar un programa');
        
        $items_validos = [];
        for ($i = 0; $i < count($rubros); $i++) {
            $id_rubro = !empty($rubros[$i]) ? intval($rubros[$i]) : 0;
            $monto = !empty($montos[$i]) ? floatval($montos[$i]) : 0;
            $obs = trim($observaciones[$i] ?? '');
            if ($id_rubro > 0 && $monto > 0) {
                $items_validos[] = ['id_rubro' => $id_rubro, 'monto' => $monto, 'observaciones' => $obs];
            }
        }
        if (empty($items_validos)) throw new Exception('Debe agregar al menos un rubro válido');

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO gasto (fecha_gasto, monto, descripcion, id_mantenedor, id_tecnico, id_rubro, id_periodo, id_programa, comprobante, fecha_registro, estado) VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, NOW(), 1)");
        
        $ids_gastos = [];
        foreach ($items_validos as $item) {
            $stmt->execute([$fecha_gasto, $item['monto'], $item['observaciones'], $id_mantenedor, $item['id_rubro'], $id_periodo, $id_programa, $ruta_comprobante]);
            $ids_gastos[] = $pdo->lastInsertId();
        }
        $pdo->commit();
        
        $_SESSION['mensaje'] = "✅ Se registraron " . count($ids_gastos) . " rubro(s) (Gastos #" . implode(', ', $ids_gastos) . ")";
        $_SESSION['tipo_mensaje'] = 'success';
        header("Location: registroGastos.php");
        exit();
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['mensaje'] = "❌ Error BD: " . ($e->errorInfo[2] ?? $e->getMessage());
        $_SESSION['tipo_mensaje'] = 'error';
        header("Location: registroGastos.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['mensaje'] = "❌ Error: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = 'error';
        header("Location: registroGastos.php");
        exit();
    }
}

// ============================================
// PROCESAR FORMULARIO DE GASTO - TÉCNICO
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btngasto_tecnico'])) {
    try {
        $fecha_gasto = $_POST['fecha_gasto'] ?? date('Y-m-d');
        $id_tecnico = !empty($_POST['Tecnico']) ? intval($_POST['Tecnico']) : null;
        $id_periodo = !empty($_POST['Periodo']) ? intval($_POST['Periodo']) : null;
        $id_programa = !empty($_POST['Programa']) ? intval($_POST['Programa']) : null;
        
        $rubros = $_POST['rubro'] ?? [];
        $montos = $_POST['monto_gasto'] ?? [];
        $observaciones = $_POST['observaciones'] ?? [];
        
        $ruta_comprobante = null;
        if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['comprobante'];
            if ($archivo['size'] > $maxFileSize) throw new Exception('El archivo excede 5MB');
            
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($archivo['tmp_name']);
            if (!in_array($mimeType, $allowedTypes)) throw new Exception('Tipo de archivo no permitido');
            
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) throw new Exception('Extensión no permitida');
            
            $nombreUnico = uniqid('comp_') . '_' . time() . '.' . $extension;
            $rutaDestino = $uploadDir . $nombreUnico;
            if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) throw new Exception('Error al guardar');
            $ruta_comprobante = $rutaDestino;
        }

        if (empty($fecha_gasto)) throw new Exception('La fecha es obligatoria');
        if (!$id_tecnico) throw new Exception('Debe seleccionar un técnico');
        if (!$id_periodo) throw new Exception('Debe seleccionar un periodo');
        if (!$id_programa) throw new Exception('Debe seleccionar un programa');
        
        $items_validos = [];
        for ($i = 0; $i < count($rubros); $i++) {
            $id_rubro = !empty($rubros[$i]) ? intval($rubros[$i]) : 0;
            $monto = !empty($montos[$i]) ? floatval($montos[$i]) : 0;
            $obs = trim($observaciones[$i] ?? '');
            if ($id_rubro > 0 && $monto > 0) {
                $items_validos[] = ['id_rubro' => $id_rubro, 'monto' => $monto, 'observaciones' => $obs];
            }
        }
        if (empty($items_validos)) throw new Exception('Debe agregar al menos un rubro válido');

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO gasto (fecha_gasto, monto, descripcion, id_mantenedor, id_tecnico, id_rubro, id_periodo, id_programa, comprobante, fecha_registro, estado) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, NOW(), 1)");
        
        $ids_gastos = [];
        foreach ($items_validos as $item) {
            $stmt->execute([$fecha_gasto, $item['monto'], $item['observaciones'], $id_tecnico, $item['id_rubro'], $id_periodo, $id_programa, $ruta_comprobante]);
            $ids_gastos[] = $pdo->lastInsertId();
        }
        $pdo->commit();
        
        $_SESSION['mensaje'] = "✅ Se registraron " . count($ids_gastos) . " rubro(s) (Gastos #" . implode(', ', $ids_gastos) . ")";
        $_SESSION['tipo_mensaje'] = 'success';
        header("Location: registroGastos.php");
        exit();
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['mensaje'] = "❌ Error BD: " . ($e->errorInfo[2] ?? $e->getMessage());
        $_SESSION['tipo_mensaje'] = 'error';
        header("Location: registroGastos.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['mensaje'] = "❌ Error: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = 'error';
        header("Location: registroGastos.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Distribución de Gastos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/styles.css" />
    <style>
        /* ===== ESTILOS PARA VISTA DIRECTA (SIN MODALES) ===== */
    </style>
</head>
<body>

    <!-- 🔹 SELECTOR PRINCIPAL: Elige qué formulario mostrar -->
    <div class="main-selector">
        <button type="button" class="selector-btn active" onclick="showForm('gastos', this)">
            ➕ Registrar Gasto por Empleado
        </button>
        <button type="button" class="selector-btn" onclick="showForm('periodo', this)">
            📅 Nuevo Periodo de Mantenimiento
        </button>
    </div>

    <!-- ============================================ -->
    <!-- 🔹 FORMULARIO: REGISTRO DE GASTOS -->
    <!-- ============================================ -->
    <div id="formGastos" class="form-container active">
        <div class="form-card">
            <h2 style="margin-bottom: 25px; color: #1e293b;">📝 Registrar Gasto por Empleado</h2>
            
            <!-- Tabs internos: Mantenedor / Técnico -->
            <div class="form-tabs">
                <button type="button" class="tab-btn active" onclick="switchForm('mantenedor', event)">👷 Mantenedor</button>
                <button type="button" class="tab-btn" onclick="switchForm('tecnico', event)">🔧 Técnico</button>
            </div>

            <!-- ======================================== -->
            <!-- FORMULARIO 1: MANTENEDOR -->
            <!-- ======================================== -->
            <div id="formMantenedor" class="form-section active">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="tipo_empleado" value="mantenedor">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">📍 Seleccionar Localidad</label>
                        <select name="Localidad" id="selectLocalidadMant" class="registro-select" required style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                            <option value="">Seleccione una Localidad</option>
                            <?php foreach($localidades as $localidad): ?>
                                <option value="<?php echo $localidad['id_localidad']; ?>" data-id-zona="<?php echo $localidad['id_zona']; ?>">
                                    <?php echo htmlspecialchars($localidad['nombre_localidad']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #64748b; font-size: 12px;">Se filtrarán los mantenedores por zona</small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">📅 Fecha del gasto</label>
                        <input name="fecha_gasto" type="date" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                    </div>

                    <h4 style="margin: 20px 0 15px; color: #1e293b;">👷 Seleccionar Mantenedor</h4>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <select name="Mantenedor" id="selectMantenedorMant" class="registro-select" required style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                            <option value="">-- Seleccione Mantenedor --</option>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Periodo</label>
                            <select name="Periodo" class="registro-select" required style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                <option value="">Seleccione un Periodo</option>
                                <?php foreach($periodo as $peri): ?>
                                    <option value="<?php echo $peri['id_periodo']; ?>"><?php echo htmlspecialchars($peri['periodo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Programa</label>
                            <select name="Programa" class="registro-select" required style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                <option value="">Seleccione un Programa</option>
                                <?php foreach($programas as $pro): ?>
                                    <option value="<?php echo $pro['id_programa']; ?>"><?php echo htmlspecialchars($pro['programa']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- 🔹 SECCIÓN DE RUBROS MÚLTIPLES -->
                    <h4 style="margin: 25px 0 15px; color: #1e293b;">📦 Categorías de Gasto</h4>
                    <div class="rubrosContainer" id="rubrosContainerMant">
                        <div class="rubro-item" data-index="0">
                            <div class="rubro-header">
                                <span class="rubro-title">Rubro #1</span>
                                <button type="button" class="btn-remove-rubro" onclick="removeRubro(this, 'mantenedor')" style="display:none;">🗑️</button>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; margin-bottom: 10px;">
                                <div class="form-group">
                                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Rubro *</label>
                                    <select name="rubro[]" class="registro-select" required style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                        <option value="">Seleccione un Rubro</option>
                                        <?php foreach($rubros as $ru): ?>
                                            <option value="<?php echo $ru['id_rubro']; ?>"><?php echo htmlspecialchars($ru['nombre_rubro']); ?></option>
                                        <?php endforeach; ?>    
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Monto *</label>
                                    <input type="number" name="monto_gasto[]" step="0.01" min="0.01" placeholder="$0.00" required onchange="calcularTotal('mantenedor')" style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                </div>
                                <div class="form-group" style="display:flex; align-items:flex-end;">
                                    <button type="button" class="btn-add-rubro" onclick="addRubro('mantenedor')" style="padding: 8px 16px;">+ Agregar</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Observaciones</label>
                                <textarea name="observaciones[]" rows="2" placeholder="Detalles adicionales..." style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 8px; resize: vertical;"></textarea>
                            </div>
                            <hr style="border:0; border-top:1px dashed #e2e8f0; margin:15px 0;">
                        </div>
                    </div>

                    <div class="total-display">
                        <strong>💰 Total del Gasto:</strong>
                        <span id="totalDisplayMant">$0.00</span>
                    </div>

                    <!-- Comprobante -->
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">📎 Comprobante (Opcional)</label>
                        <input type="file" name="comprobante" id="comprobanteMant" accept="image/*,application/pdf" onchange="previewFile(this, 'mantenedor')" style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 8px;">
                        <div id="fileInfoMant" style="margin-top: 10px; display: none;">
                            <strong>Archivo:</strong> <span id="fileNameMant"></span><br>
                            <small id="fileSizeMant"></small>
                            <div id="imagePreviewMant"></div>
                        </div>
                    </div>

                    <button type="submit" name="btngasto_mantenedor" class="btn-primary" style="background:#0f172a;">
                        💾 Registrar Gastos de Mantenedor
                    </button>
                </form>
            </div>

            <!-- ======================================== -->
            <!-- FORMULARIO 2: TÉCNICO -->
            <!-- ======================================== -->
            <div id="formTecnico" class="form-section">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="tipo_empleado" value="tecnico">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">📍 Seleccionar Localidad</label>
                        <select name="Localidad" id="selectLocalidadTec" class="registro-select" required style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                            <option value="">Seleccione una Localidad</option>
                            <?php foreach($localidades as $localidad): ?>
                                <option value="<?php echo $localidad['id_localidad']; ?>" data-id-zona="<?php echo $localidad['id_zona']; ?>">
                                    <?php echo htmlspecialchars($localidad['nombre_localidad']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #64748b; font-size: 12px;">Se cargarán mantenedores y técnicos de esta zona</small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">📅 Fecha del gasto</label>
                        <input name="fecha_gasto" type="date" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                    </div>

                    <h4 style="margin: 20px 0 15px; color: #1e293b;">👥 Seleccionar Equipo de Trabajo</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">👷 Mantenedor</label>
                            <select name="Mantenedor" id="selectMantenedorTec" class="registro-select" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                <option value="">-- Seleccione Mantenedor --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">🔧 Técnico *</label>
                            <select name="Tecnico" id="selectTecnicoTec" class="registro-select" required style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                <option value="">-- Seleccione Técnico --</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Periodo</label>
                            <select name="Periodo" class="registro-select" required style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                <option value="">Seleccione un Periodo</option>
                                <?php foreach($periodo as $peri): ?>
                                    <option value="<?php echo $peri['id_periodo']; ?>"><?php echo htmlspecialchars($peri['periodo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Programa</label>
                            <select name="Programa" class="registro-select" required style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                <option value="">Seleccione un Programa</option>
                                <?php foreach($programas as $pro): ?>
                                    <option value="<?php echo $pro['id_programa']; ?>"><?php echo htmlspecialchars($pro['programa']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- 🔹 SECCIÓN DE RUBROS MÚLTIPLES -->
                    <h4 style="margin: 25px 0 15px; color: #1e293b;">📦 Categorías de Gasto</h4>
                    <div class="rubrosContainer" id="rubrosContainerTec">
                        <div class="rubro-item" data-index="0">
                            <div class="rubro-header">
                                <span class="rubro-title">Rubro #1</span>
                                <button type="button" class="btn-remove-rubro" onclick="removeRubro(this, 'tecnico')" style="display:none;">🗑️</button>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; margin-bottom: 10px;">
                                <div class="form-group">
                                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Rubro *</label>
                                    <select name="rubro[]" class="registro-select" required style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                        <option value="">Seleccione un Rubro</option>
                                        <?php foreach($rubros as $ru): ?>
                                            <option value="<?php echo $ru['id_rubro']; ?>"><?php echo htmlspecialchars($ru['nombre_rubro']); ?></option>
                                        <?php endforeach; ?>    
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Monto *</label>
                                    <input type="number" name="monto_gasto[]" step="0.01" min="0.01" placeholder="$0.00" required onchange="calcularTotal('tecnico')" style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                </div>
                                <div class="form-group" style="display:flex; align-items:flex-end;">
                                    <button type="button" class="btn-add-rubro" onclick="addRubro('tecnico')" style="padding: 8px 16px;">+ Agregar</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Observaciones</label>
                                <textarea name="observaciones[]" rows="2" placeholder="Detalles adicionales..." style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 8px; resize: vertical;"></textarea>
                            </div>
                            <hr style="border:0; border-top:1px dashed #e2e8f0; margin:15px 0;">
                        </div>
                    </div>

                    <div class="total-display">
                        <strong>💰 Total del Gasto:</strong>
                        <span id="totalDisplayTec">$0.00</span>
                    </div>

                    <!-- Comprobante -->
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">📎 Comprobante (Opcional)</label>
                        <input type="file" name="comprobante" id="comprobanteTec" accept="image/*,application/pdf" onchange="previewFile(this, 'tecnico')" style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 8px;">
                        <div id="fileInfoTec" style="margin-top: 10px; display: none;">
                            <strong>Archivo:</strong> <span id="fileNameTec"></span><br>
                            <small id="fileSizeTec"></small>
                            <div id="imagePreviewTec"></div>
                        </div>
                    </div>

                    <button type="submit" name="btngasto_tecnico" class="btn-primary" style="background:#7c3aed;">
                        💾 Registrar Gastos de Técnico
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 🔹 FORMULARIO: PERIODO DE MANTENIMIENTO -->
    <!-- ============================================ -->
    <div id="formPeriodo" class="form-container">
        <div class="form-card">
            <h2 style="margin-bottom: 20px; color: #1e293b;">📅 Nuevo Periodo de Mantenimiento</h2>
            <form method="POST">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Fecha de salida</label>
                    <input name="feini" type="date" required style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Fecha de término</label>
                    <input name="fefin" type="date" required style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Zona</label>
                    <select name="zona" required style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                        <option value="">Seleccione una Zona</option>
                        <?php foreach($zonas as $zona): ?>
                            <option value="<?php echo $zona['id_zona']; ?>"><?php echo htmlspecialchars($zona['nombre_zona']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="btnperiodo" class="btn-primary btn-periodo">
                    + Añadir Periodo
                </button>
            </form>
        </div>
    </div>

    <footer class="footer" style="padding: 20px; text-align: center; color: #64748b; margin-top: 40px;">
        <p>© 2026 Soluciones de Tecnología Grupo Dos | Todos los derechos reservados</p>
    </footer>

    <!-- ============================================ -->
    <!-- JAVASCRIPT -->
    <!-- ============================================ -->
    <script>
        // ===== FUNCIONES COMUNES =====
        const MAX_FILE_SIZE = 5 * 1024 * 1024;
        const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

        function previewFile(input, tipo) {
            const file = input.files[0];
            const fileInfo = document.getElementById(`fileInfo${tipo === 'mantenedor' ? 'Mant' : 'Tec'}`);
            const fileName = document.getElementById(`fileName${tipo === 'mantenedor' ? 'Mant' : 'Tec'}`);
            const fileSize = document.getElementById(`fileSize${tipo === 'mantenedor' ? 'Mant' : 'Tec'}`);
            const imagePreview = document.getElementById(`imagePreview${tipo === 'mantenedor' ? 'Mant' : 'Tec'}`);
            
            if (!file) return;
            if (!ALLOWED_TYPES.includes(file.type)) {
                alert('❌ Tipo de archivo no permitido');
                input.value = '';
                return;
            }
            if (file.size > MAX_FILE_SIZE) {
                alert('❌ El archivo excede 5MB');
                input.value = '';
                return;
            }
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.style.display = 'block';
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = e => imagePreview.innerHTML = `<img src="${e.target.result}" style="max-width: 200px; margin-top: 10px; border-radius: 8px;">`;
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                imagePreview.innerHTML = '<div style="font-size:40px; margin-top:10px;">📄</div><small>PDF</small>';
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
        }

        // ===== FUNCIONES PARA RUBROS DINÁMICOS =====
        const rubroCounters = { mantenedor: 1, tecnico: 1 };

        function addRubro(tipo) {
            const suffix = tipo === 'mantenedor' ? 'Mant' : 'Tec';
            const container = document.getElementById(`rubrosContainer${suffix}`);
            const newIndex = rubroCounters[tipo]++;
            const firstSelect = container.querySelector('select[name="rubro[]"]');
            const rubroOptions = firstSelect.innerHTML;

            const newItem = document.createElement('div');
            newItem.className = 'rubro-item new';
            newItem.innerHTML = `
                <div class="rubro-header">
                    <span class="rubro-title">Rubro #${newIndex + 1}</span>
                    <button type="button" class="btn-remove-rubro" onclick="removeRubro(this, '${tipo}')">🗑️ Eliminar</button>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; margin-bottom: 10px;">
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Rubro *</label>
                        <select name="rubro[]" class="registro-select" required style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 8px;">${rubroOptions}</select>
                    </div>
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Monto *</label>
                        <input type="number" name="monto_gasto[]" step="0.01" min="0.01" placeholder="$0.00" required onchange="calcularTotal('${tipo}')" style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 8px;">
                    </div>
                    <div class="form-group" style="display:flex; align-items:flex-end;">
                        <button type="button" class="btn-remove-rubro" onclick="removeRubro(this, '${tipo}')" style="background:#ef4444; color:white; width:100%;">🗑️</button>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">Observaciones</label>
                    <textarea name="observaciones[]" rows="2" placeholder="Detalles..." style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 8px;"></textarea>
                </div>
                <hr style="border:0; border-top:1px dashed #e2e8f0; margin:15px 0;">
            `;
            container.appendChild(newItem);
            container.querySelectorAll('.btn-remove-rubro').forEach(btn => btn.style.display = 'inline-block');
            updateAddButtonVisibility(tipo);
            calcularTotal(tipo);
        }

        function removeRubro(button, tipo) {
            const suffix = tipo === 'mantenedor' ? 'Mant' : 'Tec';
            const container = document.getElementById(`rubrosContainer${suffix}`);
            const items = container.querySelectorAll('.rubro-item');
            if (items.length <= 1) {
                alert('⚠️ Debe haber al menos un rubro');
                return;
            }
            if (confirm('¿Eliminar este rubro?')) {
                button.closest('.rubro-item').remove();
                updateAddButtonVisibility(tipo);
                calcularTotal(tipo);
            }
        }

        function updateAddButtonVisibility(tipo) {
            const suffix = tipo === 'mantenedor' ? 'Mant' : 'Tec';
            const container = document.getElementById(`rubrosContainer${suffix}`);
            const items = container.querySelectorAll('.rubro-item');
            const firstAddBtn = container.querySelector('.btn-add-rubro');
            if (items.length >= 10 && firstAddBtn) {
                firstAddBtn.closest('.form-group').style.display = 'none';
            } else if (firstAddBtn) {
                firstAddBtn.closest('.form-group').style.display = 'flex';
            }
        }

        function calcularTotal(tipo) {
            const suffix = tipo === 'mantenedor' ? 'Mant' : 'Tec';
            const montos = document.querySelectorAll(`#rubrosContainer${suffix} input[name="monto_gasto[]"]`);
            let total = 0;
            montos.forEach(input => total += parseFloat(input.value) || 0);
            document.getElementById(`totalDisplay${suffix}`).textContent = '$' + total.toLocaleString('es-MX', { minimumFractionDigits: 2 });
        }

        // ===== CAMBIO DE FORMULARIO PRINCIPAL =====
        function showForm(formType, btn) {
            document.querySelectorAll('.selector-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.form-container').forEach(fc => fc.classList.remove('active'));
            document.getElementById(`form${formType === 'gastos' ? 'Gastos' : 'Periodo'}`).classList.add('active');
            if (formType === 'gastos') {
                calcularTotal('mantenedor');
                calcularTotal('tecnico');
            }
        }

        // ===== CAMBIO DE TABS INTERNOS (Mantenedor/Técnico) =====
        function switchForm(tipo, event) {
            event.preventDefault();
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            document.querySelectorAll('.form-section').forEach(sec => sec.classList.remove('active'));
            document.getElementById(`form${tipo === 'mantenedor' ? 'Mantenedor' : 'Tecnico'}`).classList.add('active');
            calcularTotal('mantenedor');
            calcularTotal('tecnico');
        }

        // ===== CARGA DE EMPLEADOS - AMBOS FORMULARIOS =====
        async function cargarMantenedoresPorZona(idZona, tipo) {
            const suffix = tipo === 'mantenedor' ? 'Mant' : 'Tec';
            const select = document.getElementById(`selectMantenedor${suffix}`);
            if (!select) return;
            
            select.disabled = true;
            select.innerHTML = '<option>Cargando...</option>';
            try {
                const res = await fetch(`api/get_mantenedores.php?id_zona=${idZona}`);
                const data = await res.json();
                if (data.success) {
                    select.innerHTML = '<option value="">-- Seleccione --</option>';
                    data.data.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.id_mantenedor;
                        opt.textContent = item.nombre;
                        select.appendChild(opt);
                    });
                    select.disabled = false;
                    
                    // ✅ Si es formulario técnico y ya hay un mantenedor seleccionado, preseleccionar técnico
                    if (tipo === 'tecnico' && select.value !== '') {
                        seleccionarPrimerTecnicoDisponible();
                    }
                }
            } catch (e) { console.error(e); }
        }

        async function cargarTecnicosPorZona(idZona) {
            const select = document.getElementById('selectTecnicoTec');
            if (!select) return;
            
            select.disabled = true;
            select.innerHTML = '<option>Cargando...</option>';
            try {
                const res = await fetch(`api/get_tecnico.php?id_zona=${idZona}`);
                const data = await res.json();
                if (data.success) {
                    select.innerHTML = '<option value="">-- Seleccione Técnico --</option>';
                    data.data.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.id_tecnico;
                        opt.textContent = item.nombre;
                        select.appendChild(opt);
                    });
                    select.disabled = false;
                    
                    // ✅ Si hay un mantenedor seleccionado, preseleccionar primer técnico disponible
                    const selectMant = document.getElementById('selectMantenedorTec');
                    if (selectMant && selectMant.value !== '') {
                        seleccionarPrimerTecnicoDisponible();
                    }
                }
            } catch (e) { console.error(e); }
        }

        // ✅ FUNCIÓN NUEVA: Selecciona el primer técnico disponible al cambiar mantenedor
        function seleccionarPrimerTecnicoDisponible() {
            const selectTec = document.getElementById('selectTecnicoTec');
            const selectMant = document.getElementById('selectMantenedorTec');

            // Verificaciones de seguridad
            if (selectTec && selectMant && 
                selectTec.options.length > 1 && 
                !selectTec.disabled && 
                selectMant.value !== '') {
                
                // Seleccionar la primera opción real (índice 1, el 0 es "-- Seleccione --")
                selectTec.value = selectTec.options[1].value;

                // 🎨 Feedback visual temporal (borde verde)
                selectTec.style.borderColor = '#10b981';
                selectTec.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.2)';
                setTimeout(() => {
                    selectTec.style.borderColor = '';
                    selectTec.style.boxShadow = '';
                }, 1200);
            }
        }

        // ===== INICIALIZACIÓN =====
        document.addEventListener('DOMContentLoaded', function() {
            calcularTotal('mantenedor');
            calcularTotal('tecnico');
            
            document.querySelectorAll('.btn-remove-rubro').forEach(btn => btn.style.display = 'none');

            // 🟢 FORMULARIO MANTENEDOR: Localidad → Mantenedor
            const selectLocalidadMant = document.getElementById('selectLocalidadMant');
            if (selectLocalidadMant) {
                selectLocalidadMant.addEventListener('change', function() {
                    const idZona = this.options[this.selectedIndex]?.getAttribute('data-id-zona');
                    const selectMant = document.getElementById('selectMantenedorMant');
                    selectMant.innerHTML = '<option value="">-- Seleccione --</option>';
                    selectMant.disabled = true;
                    if (idZona) cargarMantenedoresPorZona(idZona, 'mantenedor');
                });
            }

            // 🔵 FORMULARIO TÉCNICO: Localidad → Mantenedor + Técnico
            const selectLocalidadTec = document.getElementById('selectLocalidadTec');
            if (selectLocalidadTec) {
                selectLocalidadTec.addEventListener('change', function() {
                    const idZona = this.options[this.selectedIndex]?.getAttribute('data-id-zona');
                    
                    const selectMant = document.getElementById('selectMantenedorTec');
                    const selectTec = document.getElementById('selectTecnicoTec');
                    
                    selectMant.innerHTML = '<option value="">-- Seleccione --</option>';
                    selectMant.disabled = true;
                    selectTec.innerHTML = '<option value="">-- Seleccione --</option>';
                    selectTec.disabled = true;
                    
                    if (idZona) {
                        Promise.all([
                            cargarMantenedoresPorZona(idZona, 'tecnico'),
                            cargarTecnicosPorZona(idZona)
                        ]);
                    }
                });
            }

            // ✅ NUEVO: Listener para preseleccionar técnico al cambiar mantenedor (formulario técnico)
            const selectMantenedorTec = document.getElementById('selectMantenedorTec');
            if (selectMantenedorTec) {
                selectMantenedorTec.addEventListener('change', function() {
                    // Solo ejecutar si hay opciones cargadas en el select de técnicos
                    const selectTec = document.getElementById('selectTecnicoTec');
                    if (selectTec && selectTec.options.length > 1 && !selectTec.disabled) {
                        seleccionarPrimerTecnicoDisponible();
                    }
                });
            }
        });
    </script>
<?php require_once 'includes/footer.php'; ?>