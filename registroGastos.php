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

        $stmtMax = $pdo->query("SELECT MAX(id_periodo) as max_id FROM periodo");
        $resultado = $stmtMax->fetch(PDO::FETCH_ASSOC);
        $id_periodo = $resultado['max_id'] ? intval($resultado['max_id']) + 1 : 1;
        
        if (empty($feini)) throw new Exception('La fecha de inicio es obligatoria');
        if (empty($fefin)) throw new Exception('La fecha de término es obligatoria');
        if ($id_zona <= 0) throw new Exception('La zona es obligatoria');
        if ($feini > $fefin) throw new Exception('La fecha de inicio no puede ser mayor a la fecha de término');
        if ($id_periodo <= 0) throw new Exception('No se pudo generar un ID válido para el período');
        
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO periodo (id_periodo, fecha_inicio, fecha_fin, id_zona, estado) VALUES (?, ?, ?, ?, 'EN PROCESO')");
        $stmt->execute([intval($id_periodo), $feini, $fefin, $id_zona]);
        $pdo->commit();
        
        $_SESSION['mensaje'] = "✅ Periodo registrado correctamente";
        $_SESSION['tipo_mensaje'] = 'success';
        header("Location: registroGastos.php");
        exit();

    } catch (PDOException $e) {
        error_log("Error PDO en periodo: " . $e->getMessage());
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['mensaje'] = "❌ Error BD: " . $e->getMessage();
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
        $id_localidad = !empty($_POST['Localidad']) ? intval($_POST['Localidad']) : null;
        
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
        if (!$id_localidad) throw new Exception('Debe seleccionar una localidad');
        
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
        $stmt = $pdo->prepare("INSERT INTO gasto (fecha_gasto, monto, descripcion, id_mantenedor, id_tecnico, id_rubro, id_periodo, id_programa, id_localidad, comprobante, fecha_registro, estado) VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, NOW(), 1)");
        
        $ids_gastos = [];
        foreach ($items_validos as $item) {
            $stmt->execute([$fecha_gasto, $item['monto'], $item['observaciones'], $id_mantenedor, $item['id_rubro'], $id_periodo, $id_programa, $id_localidad, $ruta_comprobante]);
            $ids_gastos[] = $pdo->lastInsertId();
        }
        $pdo->commit();
        
        $_SESSION['mensaje'] = "✅ Se registraron " . count($ids_gastos) . " rubro(s)";
        $_SESSION['tipo_mensaje'] = 'success';
        header("Location: registroGastos.php");
        exit();
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['mensaje'] = "❌ Error BD: " . $e->getMessage();
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
        $id_localidad = !empty($_POST['Localidad']) ? intval($_POST['Localidad']) : null;
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
        if (!$id_localidad) throw new Exception('Debe seleccionar una localidad');
        
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
        $stmt = $pdo->prepare("INSERT INTO gasto (fecha_gasto, monto, descripcion, id_mantenedor, id_tecnico, id_rubro, id_periodo, id_programa, id_localidad, comprobante, fecha_registro, estado) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, NOW(), 1)");
        
        $ids_gastos = [];
        foreach ($items_validos as $item) {
            $stmt->execute([$fecha_gasto, $item['monto'], $item['observaciones'], $id_tecnico, $item['id_rubro'], $id_periodo, $id_programa, $id_localidad, $ruta_comprobante]);
            $ids_gastos[] = $pdo->lastInsertId();
        }
        $pdo->commit();
        
        $_SESSION['mensaje'] = "✅ Se registraron " . count($ids_gastos) . " rubro(s)";
        $_SESSION['tipo_mensaje'] = 'success';
        header("Location: registroGastos.php");
        exit();
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['mensaje'] = "❌ Error BD: " . $e->getMessage();
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
    <!-- ✅ SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal2-popup { font-family: 'Outfit', sans-serif !important; border-radius: 16px !important; }
        .swal2-confirm { border-radius: 8px !important; font-weight: 600 !important; }
        
        /* ✅ CSS CRÍTICO PARA MOSTRAR/OCULTAR FORMULARIOS */
        .form-container {
            display: none !important;
            animation: fadeIn 0.3s ease;
        }
        .form-container.active {
            display: block !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <!-- 🔹 SELECTOR PRINCIPAL -->
    <div class="main-selector">
        <button type="button" class="selector-btn active" onclick="showForm('gastos', this)">➕ Registrar Gasto</button>
        <button type="button" class="selector-btn" onclick="showForm('periodo', this)">📅 Nuevo Periodo</button>
    </div>

    <!-- 🔹 FORMULARIO: REGISTRO DE GASTOS -->
    <div id="formGastos" class="form-container active">
        <div class="form-card">
            <h2 style="margin-bottom: 25px; color: #1e293b;">📝 Registrar Gasto por Empleado</h2>
            
            <div class="form-tabs">
                <button type="button" class="tab-btn active" onclick="switchForm('mantenedor', event)">👷 Mantenedor</button>
                <button type="button" class="tab-btn" onclick="switchForm('tecnico', event)">🔧 Técnico</button>
            </div>

            <!-- FORMULARIO MANTENEDOR -->
            <div id="formMantenedor" class="form-section active">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="tipo_empleado" value="mantenedor">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>📍 Localidad</label>
                        <select name="Localidad" id="selectLocalidadMant" class="registro-select" required onchange="cargarMantenedores(this.value, 'mantenedor')">
                            <option value="">Seleccione una Localidad</option>
                            <?php foreach($localidades as $loc): ?>
                                <option value="<?= $loc['id_localidad'] ?>" data-zona="<?= $loc['id_zona'] ?>"><?= htmlspecialchars($loc['nombre_localidad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>📅 Fecha</label>
                        <input name="fecha_gasto" type="date" value="<?= date('Y-m-d') ?>" required class="registro-select">
                    </div>

                    <h4>👷 Mantenedor</h4>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <select name="Mantenedor" id="selectMantenedorMant" class="registro-select" required>
                            <option value="">-- Seleccione --</option>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label>Periodo</label>
                            <select name="Periodo" class="registro-select" required>
                                <option value="">Seleccione</option>
                                <?php foreach($periodo as $p): ?>
                                    <option value="<?= $p['id_periodo'] ?>"><?= htmlspecialchars($p['periodo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Programa</label>
                            <select name="Programa" class="registro-select" required>
                                <option value="">Seleccione</option>
                                <?php foreach($programas as $pr): ?>
                                    <option value="<?= $pr['id_programa'] ?>"><?= htmlspecialchars($pr['programa']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h4>📦 Rubros</h4>
                    <div id="rubrosContainerMant">
                        <div class="rubro-item">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                <select name="rubro[]" class="registro-select" required>
                                    <option value="">Rubro</option>
                                    <?php foreach($rubros as $r): ?>
                                        <option value="<?= $r['id_rubro'] ?>"><?= htmlspecialchars($r['nombre_rubro']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="monto_gasto[]" step="0.01" min="0.01" placeholder="$0.00" required onchange="calcularTotal('mantenedor')" class="registro-select">
                            </div>
                            <textarea name="observaciones[]" 
                                      placeholder="Escribe aquí las observaciones del gasto..." 
                                      class="registro-select" 
                                      rows="3" 
                                      style="width: 100%; min-height: 80px; resize: vertical;"></textarea>
                            <button type="button" onclick="this.parentElement.remove(); calcularTotal('mantenedor')" 
                                    style="background:#ef4444; color:white; border:none; padding:8px 16px; border-radius:8px; cursor:pointer; margin-top: 10px; align-self: start;">
                                🗑️ Eliminar
                            </button>
                        </div>
                    </div>
                    <button type="button" onclick="addRubro('mantenedor')" 
                            style="margin: 10px 0; padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        + Agregar rubro
                    </button>

                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 10px; margin: 20px 0; display: flex; justify-content: space-between; align-items: center; font-size: 18px;">
                        <strong>💰 Total del Gasto:</strong>
                        <span id="totalDisplayMant">$0.00</span>
                    </div>

                    <div class="form-group">
                        <label>📎 Comprobante (opcional)</label>
                        <input type="file" name="comprobante" accept="image/*,application/pdf" onchange="previewFile(this)" class="registro-select">
                    </div>

                    <button type="submit" name="btngasto_mantenedor" style="width: 100%; padding: 14px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: 16px;">
                        💾 Registrar Gastos de Mantenedor
                    </button>
                </form>
            </div>

            <!-- FORMULARIO TÉCNICO -->
            <div id="formTecnico" class="form-section">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="tipo_empleado" value="tecnico">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>📍 Localidad</label>
                        <select name="Localidad" id="selectLocalidadTec" class="registro-select" required onchange="cargarEquipo(this.value)">
                            <option value="">Seleccione una Localidad</option>
                            <?php foreach($localidades as $loc): ?>
                                <option value="<?= $loc['id_localidad'] ?>" data-zona="<?= $loc['id_zona'] ?>"><?= htmlspecialchars($loc['nombre_localidad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>📅 Fecha</label>
                        <input name="fecha_gasto" type="date" value="<?= date('Y-m-d') ?>" required class="registro-select">
                    </div>

                    <h4>👥 Equipo</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label>👷 Mantenedor</label>
                            <select name="Mantenedor" id="selectMantenedorTec" class="registro-select" onchange="seleccionarPrimerTecnico()">
                                <option value="">-- Seleccione --</option>
                            </select>
                        </div>
                        <div>
                            <label>🔧 Técnico *</label>
                            <select name="Tecnico" id="selectTecnicoTec" class="registro-select" required>
                                <option value="">-- Seleccione --</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label>Periodo</label>
                            <select name="Periodo" class="registro-select" required>
                                <option value="">Seleccione</option>
                                <?php foreach($periodo as $p): ?>
                                    <option value="<?= $p['id_periodo'] ?>"><?= htmlspecialchars($p['periodo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Programa</label>
                            <select name="Programa" class="registro-select" required>
                                <option value="">Seleccione</option>
                                <?php foreach($programas as $pr): ?>
                                    <option value="<?= $pr['id_programa'] ?>"><?= htmlspecialchars($pr['programa']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h4>📦 Rubros</h4>
                    <div id="rubrosContainerTec">
                        <div class="rubro-item">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                <select name="rubro[]" class="registro-select" required>
                                    <option value="">Rubro</option>
                                    <?php foreach($rubros as $r): ?>
                                        <option value="<?= $r['id_rubro'] ?>"><?= htmlspecialchars($r['nombre_rubro']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="monto_gasto[]" step="0.01" min="0.01" placeholder="$0.00" required onchange="calcularTotal('tecnico')" class="registro-select">
                            </div>
                            <textarea name="observaciones[]" 
                                      placeholder="Escribe aquí las observaciones del gasto..." 
                                      class="registro-select" 
                                      rows="3" 
                                      style="width: 100%; min-height: 80px; resize: vertical;"></textarea>
                            <button type="button" onclick="this.parentElement.remove(); calcularTotal('tecnico')" 
                                    style="background:#ef4444; color:white; border:none; padding:8px 16px; border-radius:8px; cursor:pointer; margin-top: 10px; align-self: start;">
                                🗑️ Eliminar
                            </button>
                        </div>
                    </div>
                    <button type="button" onclick="addRubro('tecnico')" 
                            style="margin: 10px 0; padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        + Agregar rubro
                    </button>

                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 10px; margin: 20px 0; display: flex; justify-content: space-between; align-items: center; font-size: 18px;">
                        <strong>💰 Total del Gasto:</strong>
                        <span id="totalDisplayTec">$0.00</span>
                    </div>

                    <div class="form-group">
                        <label>📎 Comprobante (opcional)</label>
                        <input type="file" name="comprobante" accept="image/*,application/pdf" onchange="previewFile(this)" class="registro-select">
                    </div>

                    <button type="submit" name="btngasto_tecnico" style="width: 100%; padding: 14px; background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: 16px;">
                        💾 Registrar Gastos de Técnico
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- 🔹 FORMULARIO: PERIODO DE MANTENIMIENTO (CORREGIDO) -->
    <div id="formPeriodo" class="form-container">
        <div class="form-card">
            <h2 style="margin-bottom: 20px; color: #1e293b; font-size: 20px; font-weight: 700;">
                📅 Nuevo Periodo de Mantenimiento
            </h2>
            <form method="POST">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">
                        Fecha de salida
                    </label>
                    <input name="feini" type="date" required class="registro-select">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">
                        Fecha de término
                    </label>
                    <input name="fefin" type="date" required class="registro-select">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #334155;">
                        Zona
                    </label>
                    <select name="zona" required class="registro-select">
                        <option value="">Seleccione una Zona</option>
                        <?php foreach($zonas as $zona): ?>
                            <option value="<?= $zona['id_zona'] ?>"><?= htmlspecialchars($zona['nombre_zona']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="btnperiodo" class="btn-primary btn-periodo">
                    + Añadir Periodo
                </button>
            </form>
        </div>
    </div>

    <!-- 🎨 SWEETALERT2: MENSAJES DE SESIÓN -->
    <?php if (isset($_SESSION['mensaje'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tipo = '<?= $_SESSION['tipo_mensaje'] ?? 'info' ?>';
        const msg = '<?= addslashes($_SESSION['mensaje']) ?>';
        const icon = tipo === 'success' ? 'success' : tipo === 'error' ? 'error' : 'info';
        const color = tipo === 'success' ? '#10b981' : tipo === 'error' ? '#ef4444' : '#667eea';
        
        if (tipo === 'success') {
            Swal.fire({ toast: true, position: 'top-end', icon, title: '¡Éxito!', text: msg, showConfirmButton: false, timer: 3000, timerProgressBar: true });
        } else {
            Swal.fire({ icon, title: tipo === 'error' ? '❌ Error' : '⚠️ Aviso', text: msg, confirmButtonColor: color });
        }
    });
    </script>
    <?php unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); endif; ?>

    <!-- ✅ JAVASCRIPT MINIMALISTA Y FUNCIONAL -->
    <script>
    // ===== UTILIDADES =====
    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2500, timerProgressBar: true });

    function previewFile(input) {
        const file = input.files[0];
        if (!file) return;
        if (!['image/jpeg','image/png','image/gif','application/pdf'].includes(file.type)) {
            Swal.fire({ icon: 'error', title: '❌ Tipo no permitido', text: 'Solo JPG, PNG, GIF o PDF', confirmButtonColor: '#ef4444' });
            input.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({ icon: 'error', title: '❌ Muy grande', text: 'Máximo 5MB', confirmButtonColor: '#ef4444' });
            input.value = '';
            return;
        }
        Toast.fire({ icon: 'success', title: '📎 Archivo listo' });
    }

    function formatFileSize(b) { if (!b) return '0 Bytes'; const k=1024, s=['Bytes','KB','MB','GB'], i=Math.floor(Math.log(b)/Math.log(k)); return (b/Math.pow(k,i)).toFixed(2)+' '+s[i]; }

    // ===== RUBROS DINÁMICOS =====
    const counters = { mantenedor: 1, tecnico: 1 };
    function addRubro(tipo) {
        const container = document.getElementById(`rubrosContainer${tipo === 'mantenedor' ? 'Mant' : 'Tec'}`);
        const select = container.querySelector('select[name="rubro[]"]');
        const newItem = document.createElement('div');
        newItem.className = 'rubro-item';
        newItem.innerHTML = `
            <select name="rubro[]" required>${select.innerHTML}</select>
            <input type="number" name="monto_gasto[]" step="0.01" min="0.01" placeholder="$0.00" required onchange="calcularTotal('${tipo}')">
            <textarea name="observaciones[]" placeholder="Obs..." rows="2"></textarea>
            <button type="button" onclick="this.parentElement.remove(); calcularTotal('${tipo}')" style="background:#ef4444; color:white; border:none; padding:5px 10px; border-radius:5px; cursor:pointer;">🗑️</button>
        `;
        container.appendChild(newItem);
        calcularTotal(tipo);
        Toast.fire({ icon: 'success', title: '✅ Rubro agregado' });
    }

    function calcularTotal(tipo) {
        const suffix = tipo === 'mantenedor' ? 'Mant' : 'Tec';
        let total = 0;
        document.querySelectorAll(`#rubrosContainer${suffix} input[name="monto_gasto[]"]`).forEach(inp => {
            const v = parseFloat(inp.value) || 0;
            if (v < 0) { inp.value = ''; Swal.fire({ icon:'error', title:'❌ Monto inválido', timer:1500 }); }
            total += v;
        });
        const display = document.getElementById(`totalDisplay${suffix}`);
        if (display) display.textContent = '$' + total.toLocaleString('es-MX', { minimumFractionDigits: 2 });
    }

    // ===== CAMBIO DE FORMULARIO/TABS (VERSIÓN LIMPIA) =====
    function showForm(type, btn) {
        // Actualizar botones del selector
        document.querySelectorAll('.selector-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        // Ocultar todos los contenedores de formulario
        document.querySelectorAll('.form-container').forEach(c => c.classList.remove('active'));
        
        // Mostrar el formulario correspondiente
        const targetId = `form${type === 'gastos' ? 'Gastos' : 'Periodo'}`;
        const target = document.getElementById(targetId);
        
        if (target) {
            // Forzar reflow para animación
            target.offsetHeight;
            target.classList.add('active');
            
            // Recalcular totales si es formulario de gastos
            if (type === 'gastos' && typeof calcularTotal === 'function') {
                calcularTotal('mantenedor');
                calcularTotal('tecnico');
            }
        }
    }

    function switchForm(type, e) {
        e.preventDefault();
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        e.target.classList.add('active');
        document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
        document.getElementById(`form${type === 'mantenedor' ? 'Mantenedor' : 'Tecnico'}`).classList.add('active');
    }

    // ===== CARGA DINÁMICA DE EMPLEADOS =====
    async function cargarMantenedores(idLocalidad, tipo) {
        if (!idLocalidad) return;
        const suffix = tipo === 'mantenedor' ? 'Mant' : 'Tec';
        const select = document.getElementById(`selectMantenedor${suffix}`);
        if (!select) return;
        
        select.disabled = true;
        select.innerHTML = '<option>Cargando...</option>';
        
        try {
            const locSelect = document.getElementById(tipo === 'mantenedor' ? 'selectLocalidadMant' : 'selectLocalidadTec');
            const idZona = locSelect?.options[locSelect.selectedIndex]?.getAttribute('data-zona');
            
            if (!idZona) throw new Error('No se encontró zona');
            
            const res = await fetch(`api/get_mantenedores.php?id_zona=${idZona}`);
            const data = await res.json();
            
            select.innerHTML = '<option value="">-- Seleccione --</option>';
            if (data.success && data.data?.length) {
                data.data.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id_mantenedor;
                    opt.textContent = item.nombre;
                    select.appendChild(opt);
                });
                if (tipo === 'tecnico' && select.value) seleccionarPrimerTecnico();
            } else {
                select.innerHTML = '<option value="">Sin resultados</option>';
            }
        } catch (e) {
            console.error('Error:', e);
            select.innerHTML = '<option value="">Error</option>';
        } finally {
            select.disabled = false;
        }
    }

    async function cargarTecnicos(idLocalidad) {
        if (!idLocalidad) return;
        const select = document.getElementById('selectTecnicoTec');
        if (!select) return;
        
        select.disabled = true;
        select.innerHTML = '<option>Cargando...</option>';
        
        try {
            const locSelect = document.getElementById('selectLocalidadTec');
            const idZona = locSelect?.options[locSelect.selectedIndex]?.getAttribute('data-zona');
            if (!idZona) throw new Error('No zona');
            
            const res = await fetch(`api/get_tecnico.php?id_zona=${idZona}`);
            const data = await res.json();
            
            select.innerHTML = '<option value="">-- Seleccione --</option>';
            if (data.success && data.data?.length) {
                data.data.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id_tecnico;
                    opt.textContent = item.nombre;
                    select.appendChild(opt);
                });
                if (document.getElementById('selectMantenedorTec')?.value) seleccionarPrimerTecnico();
            } else {
                select.innerHTML = '<option value="">Sin resultados</option>';
            }
        } catch (e) {
            console.error('Error:', e);
            select.innerHTML = '<option value="">Error</option>';
        } finally {
            select.disabled = false;
        }
    }

    function cargarEquipo(idLocalidad) {
        cargarMantenedores(idLocalidad, 'tecnico');
        cargarTecnicos(idLocalidad);
    }

    function seleccionarPrimerTecnico() {
        const tec = document.getElementById('selectTecnicoTec');
        const mant = document.getElementById('selectMantenedorTec');
        
        if (tec && mant && 
            tec.options.length > 1 && 
            mant.value && 
            !tec.disabled &&
            !tec.value) {
            
            tec.value = tec.options[1].value;
            tec.dispatchEvent(new Event('change'));
            Toast.fire({ icon: 'info', title: '💡 Técnico sugerido seleccionado', timer: 2000 });
            
            tec.style.borderColor = '#10b981';
            tec.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.2)';
            setTimeout(() => {
                tec.style.borderColor = '';
                tec.style.boxShadow = '';
            }, 1500);
        }
    }

    // ===== INICIALIZACIÓN =====
    document.addEventListener('DOMContentLoaded', function() {
        calcularTotal('mantenedor');
        calcularTotal('tecnico');
        
        ['Mant', 'Tec'].forEach(suffix => {
            const s = document.getElementById(`selectMantenedor${suffix}`);
            if (s) { s.innerHTML = '<option value="">-- Seleccione --</option>'; s.disabled = true; }
        });
        const tec = document.getElementById('selectTecnicoTec');
        if (tec) { tec.innerHTML = '<option value="">-- Seleccione --</option>'; tec.disabled = true; }
    });
    </script>
    
    <?php require_once 'includes/footer.php'; ?>