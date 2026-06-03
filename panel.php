<?php
// subir_comprobante.php
require_once 'config/auth.php';

// ✅ Proteger vista
if (!isLoggedIn()) {
    header("Location: /index.php");
    exit();
}

require 'conexion/conexion.php';

// ============================================
// ✅ CONFIGURACIÓN DE UPLOAD (NUEVO)
// ============================================
$uploadDir = 'uploads/comprobantes/';
$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

// Crear carpeta si no existe
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
// ============================================

// ======================================================================
// 🚀 BLOQUE AJAX: DEBE IR JUSTO DESPUÉS DE LOS REQUIRES, ANTES DEL HTML
// ======================================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_gastos' && isset($_GET['fecha'])) {
    // 1. Limpiar cualquier output anterior (espacios en blanco, warnings de PHP)
    if (ob_get_length()) ob_clean();
    
    // 2. Forzar cabecera JSON
    header('Content-Type: application/json');
    
    try {
        $fecha = $_GET['fecha'];
        $stmt = $pdo->prepare("SELECT g.id_gasto, g.monto, r.nombre_rubro, g.descripcion 
                               FROM gasto g 
                               LEFT JOIN rubro r ON g.id_rubro = r.id_rubro 
                               WHERE DATE(g.fecha_gasto) = ? AND g.estado = 1 
                               ORDER BY g.id_gasto DESC");
        $stmt->execute([$fecha]);
        $gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. Devolver JSON válido
        echo json_encode(['success' => true, 'data' => $gastos]);
    } catch (Exception $e) {
        // Si hay error de BD, devolver JSON con el error, NO HTML
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    // 4. DETENER LA EJECUCIÓN para que no se cargue el HTML de abajo
    exit();
}
// ======================================================================

require_once 'includes/header.php';

// ============================================
// PROCESAR FORMULARIO DE RUBRO
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btnAgregarRubro'])) {
    try {
        $nombre_rubro = trim($_POST['nombre_rubro'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        if (empty($nombre_rubro)) {
            throw new Exception('El nombre del rubro es obligatorio');
        }
        
        // Verificar si ya existe
        $stmt = $pdo->prepare("SELECT id_rubro FROM rubro WHERE LOWER(nombre_rubro) = LOWER(?)");
        $stmt->execute([$nombre_rubro]);
        if ($stmt->fetch()) {
            throw new Exception('Ya existe un rubro con ese nombre');
        }
        
        // ✅ Obtener el último id_rubro y sumar 1
        $stmtMax = $pdo->query("SELECT MAX(id_rubro) as max_id FROM rubro");
        $resultado = $stmtMax->fetch(PDO::FETCH_ASSOC);
        $nuevo_id_rubro = $resultado['max_id'] ? intval($resultado['max_id']) + 1 : 1;
        
        // ✅ Insertar incluyendo el id_rubro calculado
        $stmt = $pdo->prepare("INSERT INTO rubro (id_rubro, nombre_rubro, descripcion, activo) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nuevo_id_rubro, $nombre_rubro, $descripcion, $activo]);
        
        $_SESSION['mensaje'] = "✅ Rubro agregado correctamente (ID: $nuevo_id_rubro)";
        $_SESSION['tipo_mensaje'] = 'success';
        header("Location: panel.php");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['mensaje'] = "❌ Error BD: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = 'error';
        header("Location: panel.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['mensaje'] = "❌ Error: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = 'error';
        header("Location: panel.php");
        exit();
    }
}

// ============================================
// PROCESAR SUBIDA DE COMPROBANTE Y ACTUALIZACIÓN
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btnSubirComprobante'])) {
    try {
        $gastos_seleccionados = $_POST['gastos'] ?? [];
        
        // ✅ Validar que se haya seleccionado al menos un gasto
        if (empty($gastos_seleccionados)) {
            throw new Exception('Debes seleccionar al menos un gasto para adjuntar el comprobante.');
        }
        
        $ruta_comprobante = null;
        
        if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['comprobante'];
            
            // 🔍 DEBUG: Ver exactamente qué valores se están comparando
            $tamanoArchivo = intval($archivo['size']); // Asegurar que sea integer
            
            error_log("=== DEBUG UPLOAD ===");
            error_log("Tamaño archivo: $tamanoArchivo bytes (" . ($tamanoArchivo/1024) . " KB)");
            error_log("Límite máximo: $maxFileSize bytes (" . ($maxFileSize/1024/1024) . " MB)");
            error_log("¿Excede el límite? " . ($tamanoArchivo > $maxFileSize ? 'SÍ' : 'NO'));
            
            // ✅ Validar tamaño (comparación correcta)
            if ($tamanoArchivo > $maxFileSize) {
                $tamanoKB = round($tamanoArchivo / 1024, 2);
                $limiteMB = round($maxFileSize / 1024 / 1024, 2);
                throw new Exception("El archivo pesa {$tamanoKB} KB y excede el límite de {$limiteMB} MB.");
            }
            
            // ✅ Validar tipo MIME
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($archivo['tmp_name']);
            
            if (!in_array($mimeType, $allowedTypes)) {
                throw new Exception("Tipo de archivo no permitido. Se detectó: $mimeType. Solo se permiten: JPG, PNG, GIF o PDF.");
            }
            
            // ✅ Validar extensión
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) {
                throw new Exception("Extensión no permitida: .$extension. Solo se permiten: jpg, jpeg, png, gif, pdf.");
            }
            
            // ✅ Generar nombre único y mover archivo
            $nombreUnico = uniqid('comp_') . '_' . time() . '.' . $extension;
            $rutaDestino = $uploadDir . $nombreUnico;
            
            if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
                throw new Exception('Error al guardar el archivo en el servidor.');
            }
            
            $ruta_comprobante = $rutaDestino;
            
            error_log("✅ Archivo subido correctamente: $rutaDestino");
        } else {
            // Obtener código de error si hubo uno
            $errorCode = $_FILES['comprobante']['error'] ?? 'DESCONOCIDO';
            throw new Exception("Error en la subida del archivo. Código de error: $errorCode");
        }
        
        // ✅ Actualizar los gastos seleccionados: agregar comprobante y cambiar estado a 2
        $placeholders = implode(',', array_fill(0, count($gastos_seleccionados), '?'));
        $sql = "UPDATE gasto SET comprobante = ?, estado = 2 WHERE id_gasto IN ($placeholders) AND estado = 1";
        $stmt = $pdo->prepare($sql);
        
        // Los parámetros son: ruta_comprobante, seguido de todos los IDs seleccionados
        $params = array_merge([$ruta_comprobante], $gastos_seleccionados);
        $stmt->execute($params);
        
        $cantidad_actualizada = $stmt->rowCount();
        
        $_SESSION['mensaje'] = "✅ Comprobante subido. Se actualizaron $cantidad_actualizada gasto(s) a estado finalizado.";
        $_SESSION['tipo_mensaje'] = 'success';
        header("Location: panel.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['mensaje'] = "❌ Error: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = 'error';
        header("Location: panel.php");
        exit();
    }
}
// 🔍 Cargar rubros existentes
$rubros = $pdo->query("SELECT * FROM rubro ORDER BY id_rubro ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Comprobante y Gestión de Rubros</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/styles.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .page-container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
        .page-header { margin-bottom: 30px; text-align: center; }
        .page-header h1 { color: #1e293b; font-size: 28px; margin-bottom: 10px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .card-title { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 16px; font-size: 14px; font-family: inherit; border: 2px solid #e5e7eb; border-radius: 10px; background: #f9fafb; color: #1f2937; transition: all 0.2s ease; }
        .form-control:focus { outline: none; border-color: #667eea; background: white; box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1); }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; width: 100%; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.3); }
        .btn-success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .upload-container { border: 2px dashed #e5e7eb; border-radius: 12px; padding: 40px 24px; text-align: center; background: #f9fafb; margin: 16px 0 24px 0; transition: all 0.3s ease; }
        .upload-container:hover { border-color: #667eea; background: #f3f4ff; }
        .upload-icon { font-size: 48px; margin-bottom: 12px; opacity: 0.6; }
        .file-input-label { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .file-input-label:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3); }
        .file-info { margin-top: 16px; padding: 16px; background: white; border-radius: 8px; border: 1px solid #e5e7eb; display: none; text-align: left; }
        .file-info.show { display: block; }
        
        /* ✅ IMAGEN DE VISTA PREVIA - TAMAÑO LIMITADO */
        .preview-image { 
            max-width: 150px !important; 
            max-height: 150px !important;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 8px; 
            margin: 12px 0; 
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 2px solid #e5e7eb;
            display: block;
        }
        
        .rubro-list { margin-top: 20px; }
        .rubro-item { padding: 12px 16px; background: #f8fafc; border-radius: 8px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid #667eea; }
        .rubro-item .badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-secondary { background: #f1f5f9; color: #64748b; }
        
        /* Estilos para la lista de gastos */
        .gasto-item { display: flex; align-items: center; gap: 10px; padding: 10px; border-bottom: 1px solid #e5e7eb; background: white; border-radius: 6px; margin-bottom: 6px; transition: background 0.2s; }
        .gasto-item:hover { background: #f8fafc; }
        .gasto-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #667eea; }
        
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="page-container">
    <div class="page-header">
        <h1>📤 Subir Comprobante y Gestión de Rubros</h1>
        <p style="color: #64748b;">Administra comprobantes y rubros del sistema</p>
    </div>

    <div class="grid-2">
        <!-- 🔹 SECCIÓN 1: SUBIDA DE COMPROBANTE -->
        <div class="card">
            <h2 class="card-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Subir Comprobante
            </h2>
            
            <form method="POST" enctype="multipart/form-data" id="formComprobante">
                
                <!-- ✅ NUEVO: Selector de fecha para cargar gastos -->
                <div class="form-group">
                    <label for="fecha_gasto">📅 Seleccionar Fecha de los Gastos</label>
                    <input type="date" name="fecha_gasto" id="fecha_gasto" class="form-control" value="<?php echo date('Y-m-d'); ?>" required onchange="cargarGastos(this.value)">
                </div>

                <!-- ✅ NUEVO: Lista de gastos para seleccionar -->
                <div class="form-group" id="lista_gastos_container" style="display:none; max-height: 300px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; background: #f9fafb;">
                    <label style="margin-bottom: 10px; display: block; font-weight: 600;">Selecciona los gastos a finalizar:</label>
                    <div id="lista_gastos">
                        <!-- Se llena dinámicamente con JavaScript -->
                    </div>
                </div>

                <div class="upload-container">
                    <div class="upload-icon">📎</div>
                    <p style="margin-bottom: 16px; color: #64748b;">
                        Arrastra un archivo aquí o haz clic para seleccionar
                    </p>
                    <label class="file-input-label">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Seleccionar Archivo
                        <input type="file" name="comprobante" id="comprobante" 
                               accept="image/*,application/pdf" 
                               style="display: none;" 
                               onchange="previewFile(this)">
                    </label>
                    <p style="margin-top: 12px; font-size: 12px; color: #94a3b8;">
                        Formatos: JPG, PNG, GIF, PDF (Máx. 5MB)
                    </p>
                </div>

                <div id="fileInfo" class="file-info">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <div style="font-size: 32px;" id="fileIcon">📄</div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: #1e293b;" id="fileName"></div>
                            <div style="font-size: 12px; color: #64748b;" id="fileSize"></div>
                        </div>
                    </div>
                    <div id="imagePreview"></div>
                </div>

                <button type="submit" name="btnSubirComprobante" class="btn btn-success" style="margin-top: 20px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Subir Comprobante y Finalizar
                </button>
            </form>
        </div>

        <!-- 🔹 SECCIÓN 2: AGREGAR NUEVO RUBRO -->
        <div class="card">
            <h2 class="card-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Agregar Nuevo Rubro
            </h2>
            
            <form method="POST">
                <div class="form-group">
                    <label for="nombre_rubro">Nombre del Rubro *</label>
                    <input type="text" name="nombre_rubro" id="nombre_rubro" class="form-control" placeholder="Ej: Transporte, Alimentación..." required>
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea name="descripcion" id="descripcion" class="form-control" placeholder="Descripción opcional del rubro..."></textarea>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="activo" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="font-weight: 500;">Rubro activo</span>
                    </label>
                    <small style="color: #64748b; display: block; margin-top: 4px;">
                        Los rubros inactivos no aparecerán en los formularios
                    </small>
                </div>

                <button type="submit" name="btnAgregarRubro" class="btn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Agregar Rubro
                </button>
            </form>
        </div>
    </div>

    <!-- 🔹 LISTA DE RUBROS EXISTENTES -->
    <div class="card" style="margin-top: 30px;">
        <h2 class="card-title">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            Rubros Registrados (<?php echo count($rubros); ?>)
        </h2>
        
        <?php if (empty($rubros)): ?>
            <div style="text-align: center; padding: 40px; color: #94a3b8;">
                <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
                <p>No hay rubros registrados en el sistema</p>
            </div>
        <?php else: ?>
            <div class="rubro-list">
                <?php foreach($rubros as $rubro): ?>
                    <div class="rubro-item">
                        <div>
                            <strong style="color: #1e293b;">ID: <?php echo $rubro['id_rubro']; ?> - <?php echo htmlspecialchars($rubro['nombre_rubro']); ?></strong>
                            <?php if ($rubro['descripcion']): ?>
                                <br><small style="color: #64748b;"><?php echo htmlspecialchars($rubro['descripcion']); ?></small>
                            <?php endif; ?>
                        </div>
                        <span class="badge <?php echo $rubro['activo'] ? 'badge-success' : 'badge-secondary'; ?>">
                            <?php echo $rubro['activo'] ? '✓ Activo' : '○ Inactivo'; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 🎨 SWEETALERT2: MENSAJES DE SESIÓN -->
<?php if (isset($_SESSION['mensaje'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipo = '<?php echo $_SESSION['tipo_mensaje'] ?? 'info'; ?>';
    const msg = '<?php echo addslashes($_SESSION['mensaje']); ?>';
    const icon = tipo === 'success' ? 'success' : tipo === 'error' ? 'error' : 'info';
    const color = tipo === 'success' ? '#10b981' : tipo === 'error' ? '#ef4444' : '#667eea';
    
    Swal.fire({
        icon: icon,
        title: tipo === 'success' ? '¡Éxito!' : tipo === 'error' ? 'Error' : 'Aviso',
        text: msg,
        confirmButtonColor: color,
        timer: tipo === 'success' ? 3000 : null
    });
});
</script>
<?php unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); endif; ?>

<!-- ✅ JAVASCRIPT PARA PREVIEW Y CARGA DE GASTOS -->
<script>
// ===== CARGAR GASTOS POR FECHA =====
function cargarGastos(fecha) {
    if (!fecha) {
        document.getElementById('lista_gastos_container').style.display = 'none';
        return;
    }
    
    // Mostrar estado de carga
    document.getElementById('lista_gastos').innerHTML = '<p style="text-align:center; color:#64748b; padding:10px;">⏳ Cargando gastos...</p>';
    document.getElementById('lista_gastos_container').style.display = 'block';
    
    // ✅ CORRECCIÓN: Usar la ruta actual del archivo explícitamente
    const currentUrl = window.location.href.split('?')[0]; 
    const fetchUrl = `${currentUrl}?action=get_gastos&fecha=${fecha}`;
    
    fetch(fetchUrl)
        .then(res => {
            // Verificar si la respuesta es realmente JSON antes de parsear
            const contentType = res.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                throw new TypeError("El servidor no devolvió JSON. Revisa la pestaña 'Network' (Red) en F12.");
            }
            return res.json();
        })
        .then(response => {
            const container = document.getElementById('lista_gastos');
            container.innerHTML = '';
            
            if (!response.success) {
                container.innerHTML = `<p style="color: #ef4444; text-align: center; padding: 10px;">❌ Error del servidor: ${response.error}</p>`;
                return;
            }
            
            const data = response.data;
            
            if (data.length === 0) {
                container.innerHTML = '<p style="color: #64748b; text-align: center; padding: 10px;">No hay gastos pendientes (estado 1) para esta fecha.</p>';
            } else {
                data.forEach(gasto => {
                    const div = document.createElement('div');
                    div.className = 'gasto-item';
                    div.innerHTML = `
                        <input type="checkbox" name="gastos[]" value="${gasto.id_gasto}" style="width: 18px; height: 18px; cursor: pointer;">
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="color: #1e293b; font-size: 14px;">Gasto #${gasto.id_gasto}</strong>
                                <span style="color: #667eea; font-weight: 700; font-size: 15px;">$${parseFloat(gasto.monto).toFixed(2)}</span>
                            </div>
                            <small style="color: #64748b; display: block; margin-top: 4px;">
                                🏷️ ${gasto.nombre_rubro || 'Sin rubro'} 
                                ${gasto.descripcion ? ' | 📝 ' + gasto.descripcion : ''}
                            </small>
                        </div>
                    `;
                    container.appendChild(div);
                });
            }
        })
        .catch(err => {
            console.error('Error detallado:', err);
            document.getElementById('lista_gastos').innerHTML = `<p style="color: #ef4444; text-align: center; padding:10px;">❌ Error: ${err.message}</p>`;
        });
}

// Cargar gastos del día actual al iniciar la página
document.addEventListener('DOMContentLoaded', function() {
    const fechaInput = document.getElementById('fecha_gasto');
    if (fechaInput && fechaInput.value) {
        cargarGastos(fechaInput.value);
    }
});

// ===== PREVIEW DE ARCHIVOS =====
function previewFile(input) {
    const file = input.files[0];
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const fileIcon = document.getElementById('fileIcon');
    const imagePreview = document.getElementById('imagePreview');
    
    if (!file) return;
    
    // 🔍 DEBUG: Imprimir en consola el tamaño real detectado
    console.log("📦 Nombre:", file.name);
    console.log("📏 Tamaño real (bytes):", file.size);
    console.log("📏 Tamaño real (KB):", (file.size / 1024).toFixed(2));
    console.log("📏 Tipo MIME detectado:", file.type);
    
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    const maxSize = 5 * 1024 * 1024; // 5MB = 5,242,880 bytes
    
    // 1. Validar tipo de archivo (¡A veces las imágenes son .webp o .heic!)
    if (!allowedTypes.includes(file.type)) {
        Swal.fire({
            icon: 'error',
            title: '❌ Tipo de archivo no permitido',
            text: `El sistema detectó el tipo: "${file.type}".\nSolo se permiten: JPG, PNG, GIF o PDF.`,
            confirmButtonColor: '#ef4444'
        });
        input.value = '';
        return;
    }
    
    // 2. Validar tamaño (Ahora muestra el tamaño real detectado)
    if (file.size > maxSize) {
        const sizeInMB = (file.size / 1024 / 1024).toFixed(2);
        Swal.fire({
            icon: 'error',
            title: '❌ Archivo demasiado grande',
            text: `El navegador detecta que este archivo pesa ${sizeInMB} MB.\nEl máximo permitido es 5 MB.`,
            confirmButtonColor: '#ef4444'
        });
        input.value = '';
        return;
    }
    
    // Si pasa las validaciones, mostrar vista previa
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);
    fileInfo.classList.add('show');
    imagePreview.innerHTML = '';
    
    if (file.type.startsWith('image/')) {
        fileIcon.textContent = '🖼️';
        const reader = new FileReader();
        reader.onload = e => {
            // ✅ IMAGEN CON TAMAÑO LIMITADO (150x150px máx)
            imagePreview.innerHTML = `<img src="${e.target.result}" alt="Vista previa" class="preview-image" style="max-width: 150px; max-height: 150px;">`;
        };
        reader.readAsDataURL(file);
    } else if (file.type === 'application/pdf') {
        fileIcon.textContent = '📄';
        imagePreview.innerHTML = '<div style="font-size: 48px; text-align: center; margin: 12px 0;">📄</div><div style="text-align: center; color: #64748b; font-size: 12px;">Documento PDF</div>';
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
}
</script>

<?php require_once 'includes/footer.php'; ?>