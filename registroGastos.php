<?php
// registroGastos.php
require_once 'config/auth.php';

// ✅ Verificar que esté logueado
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

// 🔵 CAMBIO: Cargar localidades con su id_zona
$localidades = $pdo->query("
    SELECT id_localidad, nombre_localidad, id_zona 
    FROM localidad 
    WHERE estado = 1 
    ORDER BY nombre_localidad ASC
")->fetchAll(PDO::FETCH_ASSOC);

$programas = $pdo->query("SELECT id_programa, programa FROM programa ORDER BY programa ASC")->fetchAll(PDO::FETCH_ASSOC);
$rubros = $pdo->query("SELECT id_rubro, nombre_rubro FROM rubro WHERE activo = 1 ORDER BY nombre_rubro ASC")->fetchAll(PDO::FETCH_ASSOC);
$periodo = $pdo->query("SELECT id_periodo, concat('Entre ',fecha_inicio,' y ',fecha_fin) periodo FROM periodo WHERE estado = 'EN PROCESO' ORDER BY periodo ASC")->fetchAll(PDO::FETCH_ASSOC);

// 🔍 Cargar todos los trabajadores (mantenedores y técnicos)
$trabajadores = $pdo->query("
    SELECT id_mantenedor as id, nombre, 'Mantenedor' as tipo FROM mantenedor WHERE estado = 1
    UNION ALL
    SELECT id_tecnico as id, nombre, 'Técnico' as tipo FROM tecnico WHERE activo = 1
    ORDER BY tipo, nombre
")->fetchAll(PDO::FETCH_ASSOC);

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
        $stmt = $pdo->prepare("
            INSERT INTO periodo (id_periodo, fecha_inicio, fecha_fin, id_zona, estado) 
            VALUES (?, ?, ?, ?, 'EN PROCESO')
        ");
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
// PROCESAR FORMULARIO DE GASTO
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btngasto'])) {
    try {
        // ✅ Datos comunes para todos los rubros
        $fecha_gasto = $_POST['fecha_gasto'] ?? date('Y-m-d');
        $id_mantenedor = !empty($_POST['Mantenedor']) ? intval($_POST['Mantenedor']) : null;
        $id_tecnico = !empty($_POST['Tecnico']) ? intval($_POST['Tecnico']) : null;
        $id_periodo = !empty($_POST['Periodo']) ? intval($_POST['Periodo']) : null;
        $id_programa = !empty($_POST['Programa']) ? intval($_POST['Programa']) : null;
        
        // 🔹 CAMBIO: Arrays para múltiples rubros
        $rubros = $_POST['rubro'] ?? [];
        $montos = $_POST['monto_gasto'] ?? [];
        $observaciones = $_POST['observaciones'] ?? [];
        
        // 🔹 CAMBIO: Procesar archivo de comprobante (se comparte para todos los rubros)
        $ruta_comprobante = null;
        
        if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['comprobante'];
            
            if ($archivo['size'] > $maxFileSize) {
                throw new Exception('El archivo excede el tamaño máximo de 5MB');
            }
            
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($archivo['tmp_name']);
            
            if (!in_array($mimeType, $allowedTypes)) {
                throw new Exception('Tipo de archivo no permitido. Solo JPG, PNG, GIF o PDF');
            }
            
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) {
                throw new Exception('Extensión de archivo no permitida');
            }
            
            $nombreUnico = uniqid('comp_') . '_' . time() . '.' . $extension;
            $rutaDestino = $uploadDir . $nombreUnico;
            
            if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
                throw new Exception('Error al guardar el archivo');
            }
            
            $ruta_comprobante = $rutaDestino;
        }

        // 🔹 CAMBIO: Validaciones para múltiples rubros
        if (empty($fecha_gasto)) throw new Exception('La fecha del gasto es obligatoria');
        if (!$id_periodo) throw new Exception('Debe seleccionar un periodo');
        if (!$id_programa) throw new Exception('Debe seleccionar un programa');
        if (!$id_mantenedor && !$id_tecnico) {
            throw new Exception('Debe seleccionar al menos un empleado');
        }
        
        // Filtrar rubros completos (con rubro, monto y observación)
        $items_validos = [];
        $total_general = 0;
        
        for ($i = 0; $i < count($rubros); $i++) {
            $id_rubro = !empty($rubros[$i]) ? intval($rubros[$i]) : 0;
            $monto = !empty($montos[$i]) ? floatval($montos[$i]) : 0;
            $obs = trim($observaciones[$i] ?? '');
            
            if ($id_rubro > 0 && $monto > 0) {
                $items_validos[] = [
                    'id_rubro' => $id_rubro,
                    'monto' => $monto,
                    'observaciones' => $obs
                ];
                $total_general += $monto;
            }
        }
        
        if (empty($items_validos)) {
            throw new Exception('Debe agregar al menos un rubro con monto válido');
        }

        // 🔹 CAMBIO: Insertar múltiples registros en transacción
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO gasto (
                fecha_gasto, monto, descripcion, id_mantenedor, id_tecnico, 
                id_rubro, id_periodo, id_programa, comprobante, fecha_registro, estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
        ");
        
        $ids_gastos = [];
        
        foreach ($items_validos as $item) {
            $stmt->execute([
                $fecha_gasto, 
                $item['monto'], 
                $item['observaciones'], 
                $id_mantenedor, 
                $id_tecnico,
                $item['id_rubro'], 
                $id_periodo, 
                $id_programa, 
                $ruta_comprobante  // Mismo comprobante para todos
            ]);
            $ids_gastos[] = $pdo->lastInsertId();
        }
        
        $pdo->commit();
        
        // Mensaje con resumen
        $cantidad = count($ids_gastos);
        $ids_texto = implode(', ', $ids_gastos);
        $_SESSION['mensaje'] = "✅ Se registraron {$cantidad} rubro(s) correctamente (Gastos #{$ids_texto})";
        $_SESSION['tipo_mensaje'] = 'success';
        header("Location: registroGastos.php");
        exit();
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error PDO: " . $e->getMessage());
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
</head>
<body>

    <!-- 🔹 BOTÓN PARA ABRIR EL MODAL -->
    <button type="button" class="btn-open-modal" onclick="openModal('modalPeriodo')">
        + Nuevo Periodo de Mantenimiento
    </button>

    <!-- 🔹 ESTRUCTURA DEL MODAL -->
    <div id="modalPeriodo" class="modal-overlay" onclick="closeModalOnClick(event, 'modalPeriodo')">
        <div class="modal-content">
            
            <!-- Botón cerrar (X) -->
            <button type="button" class="modal-close" onclick="closeModal('modalPeriodo')">&times;</button>
            
            <!-- FORMULARIO DE PERIODO -->
            <form method="POST">          
                <div class="registro-contenedor">
                    <img src="assets/add.png" class="registro-icon" alt="Icono agregar">
                    <h3>Registrar un periodo de Mantenimiento</h3>
                    
                    <div>
                        <label>Fecha de salida</label>
                        <input name="feini" type="date" class="registro-input-date" required>
                    </div>
                    
                    <div>
                        <label>Fecha de término</label>
                        <input name="fefin" type="date" class="registro-input-date" required>
                    </div>
                    
                    <div>
                        <div class="form-group">
                            <label>Zona</label>
                            <select name="zona" id="selectZonaPeriodo" class="registro-select" required>
                                <option value="">Seleccione una Zona</option>
                                <?php foreach($zonas as $zona): ?>
                                    <option value="<?php echo $zona['id_zona']; ?>">
                                        <?php echo htmlspecialchars($zona['nombre_zona']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div> 
                    
                    <button type="submit" name="btnperiodo" class="btn">+ Añadir Periodo</button>
                </div>
            </form>
            
        </div>
    </div>
        
    <!-- FORMULARIO DE GASTO -->
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="registro-contenedor">
            <img src="assets/add.png" class="registro-icon" alt="">
            <h3>Registrar Gasto por Empleado</h3>

            <!-- 🔵 CAMBIO: Zona → Localidad -->
            <div class="form-group">
                <label>Seleccionar Localidad</label>
                <select name="Localidad" id="selectLocalidad" class="registro-select" required>
                    <option value="">Seleccione una Localidad</option>
                    <?php foreach($localidades as $localidad): ?>
                        <option value="<?php echo $localidad['id_localidad']; ?>" 
                                data-id-zona="<?php echo $localidad['id_zona']; ?>">
                            <?php echo htmlspecialchars($localidad['nombre_localidad']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #64748b; font-size: 12px; margin-top: 4px; display: block;">
                    La zona se determinará automáticamente según la localidad seleccionada
                </small>
            </div>

            <div class="form-group">
                <label>Fecha del gasto</label>
                <input name="fecha_gasto" type="date" class="registro-input-date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <h3>Seleccionar Empleado</h3>
            <div class="registro-grid-2">
                <div class="form-group">
                    <label>Mantenedor</label>
                    <select name="Mantenedor" id="selectMantenedor" class="registro-select">
                        <option value="">-- Seleccione Mantenedor --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Técnico</label>
                    <select name="Tecnico" id="selectTecnico" class="registro-select">
                        <option value="">-- Seleccione Técnico --</option>
                    </select>
                </div>
            </div>

            <div class="registro-grid-2">
                <div class="form-group">
                    <label>Periodo</label>
                    <select name="Periodo" class="registro-select" required>
                        <option value="">Seleccione un Periodo</option>
                        <?php foreach($periodo as $peri): ?>
                            <option value="<?php echo $peri['id_periodo']; ?>">
                                <?php echo htmlspecialchars($peri['periodo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Programa</label>
                    <select name="Programa" class="registro-select" required>
                        <option value="">Seleccione un Programa</option>
                        <?php foreach($programas as $pro): ?>
                            <option value="<?php echo $pro['id_programa']; ?>">
                                <?php echo htmlspecialchars($pro['programa']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <h3> 📦 Categorías de Gasto</h3>

<!-- Contenedor dinámico para rubros -->
<div id="rubrosContainer">
    
    <!-- 🔹 Primer rubro (plantilla base) -->
    <div class="rubro-item" data-index="0">
        <div class="rubro-header">
            <span class="rubro-title">Rubro #1</span>
            <button type="button" class="btn-remove-rubro" onclick="removeRubro(this)" title="Eliminar rubro" style="display:none;">🗑️</button>
        </div>
        
        <div class="registro-grid-3">
            <div class="form-group">
                <label>Rubro *</label>
                <select name="rubro[]" class="registro-select" required>
                    <option value="">Seleccione un Rubro</option>
                    <?php foreach($rubros as $ru): ?>
                        <option value="<?php echo $ru['id_rubro']; ?>">
                            <?php echo htmlspecialchars($ru['nombre_rubro']); ?>
                        </option>
                    <?php endforeach; ?>    
                </select>
            </div>
            <div class="form-group">
                <label>Monto *</label>
                <input type="number" name="monto_gasto[]" step="0.01" min="0.01" placeholder="$0.00" class="registro-input" required onchange="calcularTotal()">
            </div>
            <div class="form-group" style="display:flex; align-items:flex-end;">
                <button type="button" class="btn-add-rubro" onclick="addRubro()" style="width:100%; padding:12px; background:#22c55e; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600;">
                    + Agregar otro rubro
                </button>
            </div>
        </div>
        
        <div class="form-group">
            <label>Observaciones</label>
            <textarea name="observaciones[]" rows="2" placeholder="Detalles adicionales..." class="registro-textarea"></textarea>
        </div>
        
        <hr style="border:0; border-top:1px dashed #e2e8f0; margin:15px 0;">
    </div>
    
</div>

<!-- 🔹 Total calculado -->
<div class="form-group" style="background:#f8fafc; padding:15px; border-radius:8px; margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <strong style="color:#1e293b;">💰 Total del Gasto:</strong>
        <span id="totalDisplay" style="font-size:20px; font-weight:700; color:#0f172a;">$0.00</span>
    </div>
</div>

<!-- 🔹 Sección de Comprobante (se comparte para todos los rubros) -->
<div class="form-group">
    <label>📎 Comprobante (Opcional - se aplicará a todos los rubros)</label>
    
    <div class="registro-upload-container" id="dropZone">
        <p>Arrastra tu archivo aquí o</p>
        
        <div class="registro-file-input-wrapper">
            <input type="file" 
                   name="comprobante" 
                   id="comprobante" 
                   accept="image/*,application/pdf"
                   onchange="previewFile(this)">
            <label for="comprobante" class="registro-file-input-label">
                Seleccionar Archivo
            </label>
        </div>
        
        <div class="registro-file-info" id="fileInfo">
            <strong>Archivo seleccionado:</strong><br>
            <span id="fileName"></span><br>
            <small id="fileSize"></small>
            <div id="imagePreview"></div>
        </div>
    </div>
    
    <div class="registro-upload-rules">
        <strong>Requisitos:</strong>
        <ul>
            <li>Formatos: JPG, PNG, GIF o PDF</li>
            <li>Tamaño máximo: 5MB</li>
            <li>El archivo debe ser legible</li>
        </ul>
    </div>
</div>

<button type="submit" name="btngasto" class="btn" style="background:#0f172a;">
    💾 Registrar Todos los Rubros
</button>
        </div>
    </form>

    <footer class="footer">
        <p>© 2026 Soluciones de Tecnología Grupo Dos | Todos los derechos reservados</p>
    </footer>

    <script>
        // Elementos del DOM - 🔵 CAMBIO: selectZona → selectLocalidad
        const selectLocalidad = document.getElementById('selectLocalidad');
        const selectMantenedor = document.getElementById('selectMantenedor');
        const selectTecnico = document.getElementById('selectTecnico');
        const fileInput = document.getElementById('comprobante');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const imagePreview = document.getElementById('imagePreview');
        const dropZone = document.getElementById('dropZone');
        
        const MAX_FILE_SIZE = 5 * 1024 * 1024;
        const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

        function previewFile(input) {
            const file = input.files[0];
            if (!file) return;
            
            if (!ALLOWED_TYPES.includes(file.type)) {
                alert('❌ Tipo de archivo no permitido. Solo JPG, PNG, GIF o PDF');
                input.value = '';
                return;
            }
            
            if (file.size > MAX_FILE_SIZE) {
                alert('❌ El archivo excede el tamaño máximo de 5MB');
                input.value = '';
                return;
            }
            
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.classList.add('show');
            
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="registro-preview-image">`;
                };
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                imagePreview.innerHTML = '<div style="font-size:40px">📄</div><small>Archivo PDF</small>';
            }
        }
        
        function removeFile() {
            fileInput.value = '';
            fileInfo.classList.remove('show');
            imagePreview.innerHTML = '';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('registro-upload-container-hover');
        });
        
        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('registro-upload-container-hover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('registro-upload-container-hover');
            
            const file = e.dataTransfer.files[0];
            if (file) {
                fileInput.files = e.dataTransfer.files;
                previewFile(fileInput);
            }
        });

        // 🔵 CAMBIO: Función modificada para recibir idZona
        async function cargarMantenedores(idZona) {
            try {
                selectMantenedor.disabled = true;
                selectMantenedor.innerHTML = '<option value="">Cargando...</option>';
                const response = await fetch(`api/get_mantenedores.php?id_zona=${idZona}`);
                const result = await response.json();
                if (result.success) {
                    llenarSelect(selectMantenedor, result.data, 'id_mantenedor', 'nombre');
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        // 🔵 CAMBIO: Función modificada para recibir idZona
        async function cargarTecnicos(idZona) {
            try {
                selectTecnico.disabled = true;
                selectTecnico.innerHTML = '<option value="">Cargando...</option>';
                const response = await fetch(`api/get_tecnico.php?id_zona=${idZona}`);
                const result = await response.json();
                if (result.success) {
                    llenarSelect(selectTecnico, result.data, 'id_tecnico', 'nombre');
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function llenarSelect(selectElement, datos, valueField, textField) {
            selectElement.innerHTML = '<option value="">-- Seleccione --</option>';
            if (datos && datos.length > 0) {
                datos.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item[valueField];
                    option.textContent = item[textField];
                    selectElement.appendChild(option);
                });
                selectElement.disabled = false;
            }
        }

        // 🔵 CAMBIO: Event listener modificado para obtener id_zona desde la localidad
        selectLocalidad?.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const idZona = selectedOption.getAttribute('data-id-zona');
            
            // Limpiar selects de empleados
            selectMantenedor.innerHTML = '<option value="">-- Seleccione --</option>';
            selectMantenedor.disabled = true;
            selectTecnico.innerHTML = '<option value="">-- Seleccione --</option>';
            selectTecnico.disabled = true;
            
            if (idZona) {
                // Cargar empleados filtrados por la zona de la localidad seleccionada
                Promise.all([cargarMantenedores(idZona), cargarTecnicos(idZona)]);
            }
        });
    </script>
    <script>
    // 🔹 Abrir modal
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
        document.body.style.overflow = 'hidden'; // Evitar scroll en el fondo
    }
    
    // 🔹 Cerrar modal
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
        document.body.style.overflow = ''; // Restaurar scroll
    }
    
    // 🔹 Cerrar modal al hacer clic fuera del contenido
    function closeModalOnClick(event, modalId) {
        if (event.target.id === modalId) {
            closeModal(modalId);
        }
    }
    
    // 🔹 Cerrar modal con tecla ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const activeModal = document.querySelector('.modal-overlay.active');
            if (activeModal) {
                closeModal(activeModal.id);
            }
        }
    });
      // 🔹 Contador para índices únicos
    let rubroCounter = 1;
    
    // 🔹 Función para agregar nuevo rubro
    function addRubro() {
        const container = document.getElementById('rubrosContainer');
        const newIndex = rubroCounter++;
        
        // Obtener opciones de rubros desde el primer select
        const firstSelect = document.querySelector('select[name="rubro[]"]');
        const rubroOptions = firstSelect.innerHTML;
        
        // Crear nuevo item de rubro
        const newItem = document.createElement('div');
        newItem.className = 'rubro-item new';
        newItem.dataset.index = newIndex;
        
        newItem.innerHTML = `
            <div class="rubro-header">
                <span class="rubro-title">Rubro #${newIndex + 1}</span>
                <button type="button" class="btn-remove-rubro" onclick="removeRubro(this)" title="Eliminar rubro">🗑️ Eliminar</button>
            </div>
            
            <div class="registro-grid-3">
                <div class="form-group">
                    <label>Rubro *</label>
                    <select name="rubro[]" class="registro-select" required>
                        ${rubroOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto *</label>
                    <input type="number" name="monto_gasto[]" step="0.01" min="0.01" placeholder="$0.00" class="registro-input" required onchange="calcularTotal()">
                </div>
                <div class="form-group" style="display:flex; align-items:flex-end;">
                    <button type="button" class="btn-remove-rubro" onclick="removeRubro(this)" style="width:100%; background:#ef4444; color:white;">
                        🗑️ Eliminar
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones[]" rows="2" placeholder="Detalles adicionales..." class="registro-textarea"></textarea>
            </div>
            
            <hr style="border:0; border-top:1px dashed #e2e8f0; margin:15px 0;">
        `;
        
        container.appendChild(newItem);
        
        // Mostrar botones de eliminar en todos los items
        document.querySelectorAll('.btn-remove-rubro').forEach(btn => {
            btn.style.display = 'inline-block';
        });
        
        // Ocultar botón "Agregar" del primer item si hay más de uno
        updateAddButtonVisibility();
        
        // Calcular total
        calcularTotal();
    }
    
    // 🔹 Función para eliminar rubro
    function removeRubro(button) {
        const rubroItem = button.closest('.rubro-item');
        const items = document.querySelectorAll('.rubro-item');
        
        // No permitir eliminar si es el único rubro
        if (items.length <= 1) {
            alert('⚠️ Debe haber al menos un rubro registrado');
            return;
        }
        
        // Confirmar eliminación
        if (confirm('¿Estás seguro de eliminar este rubro?')) {
            rubroItem.style.animation = 'slideIn 0.2s ease reverse';
            setTimeout(() => {
                rubroItem.remove();
                updateAddButtonVisibility();
                calcularTotal();
            }, 200);
        }
    }
    
    // 🔹 Actualizar visibilidad del botón "Agregar"
    function updateAddButtonVisibility() {
        const items = document.querySelectorAll('.rubro-item');
        const firstItem = items[0];
        const addBtn = firstItem?.querySelector('.btn-add-rubro');
        
        if (items.length >= 3) {
            // Limitar a 3 rubros por gasto (ajustable)
            if (addBtn) addBtn.closest('.form-group').style.display = 'none';
        } else {
            if (addBtn) addBtn.closest('.form-group').style.display = 'flex';
        }
    }
    
    // 🔹 Calcular total de todos los montos
    function calcularTotal() {
        const montos = document.querySelectorAll('input[name="monto_gasto[]"]');
        let total = 0;
        
        montos.forEach(input => {
            const valor = parseFloat(input.value) || 0;
            total += valor;
        });
        
        // Formatear como moneda
        document.getElementById('totalDisplay').textContent = 
            '$' + total.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    
    // 🔹 Inicializar: ocultar botones de eliminar al cargar
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.btn-remove-rubro').forEach(btn => {
            btn.style.display = 'none';
        });
        calcularTotal();
    });
</script>
<?php require_once 'includes/footer.php'; ?>