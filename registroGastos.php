<?php
session_start();
require 'conexion/conexion.php';

// Configuración de upload
$uploadDir = 'uploads/comprobantes/';
$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

// Crear carpeta si no existe
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// ✅ Cargar datos iniciales para selects principales
$grupos = $pdo->query("SELECT id_grupo, nombre_grupo FROM grupo WHERE activo = 1 ORDER BY nombre_grupo ASC")->fetchAll(PDO::FETCH_ASSOC);
$programas = $pdo->query("SELECT id_programa, programa FROM programa ORDER BY programa ASC")->fetchAll(PDO::FETCH_ASSOC);
$rubros = $pdo->query("SELECT id_rubro, nombre_rubro FROM rubro WHERE activo = 1 ORDER BY nombre_rubro ASC")->fetchAll(PDO::FETCH_ASSOC);
$localidades = $pdo->query("SELECT id_localidad, nombre_localidad FROM localidad WHERE estado = 1 ORDER BY nombre_localidad ASC")->fetchAll(PDO::FETCH_ASSOC);
$periodo = $pdo->query("SELECT id_periodo, concat('Entre ',fecha_inicio,' y ',fecha_fin) periodo FROM periodo WHERE estado = 'EN PROCESO' ORDER BY periodo ASC")->fetchAll(PDO::FETCH_ASSOC);

// ✅ Función helper para generar options
function generarOptions($datos, $valueField, $textField, $selected = '') {
    $options = '<option value="">-- Seleccione --</option>';
    foreach($datos as $item) {
        $selectedAttr = ($item[$valueField] == $selected) ? 'selected' : '';
        $options .= '<option value="' . $item[$valueField] . '" ' . $selectedAttr . '>';
        $options .= htmlspecialchars($item[$textField]);
        $options .= '</option>';
    }
    return $options;
}

// ============================================
// PROCESAR FORMULARIO DE PERIODO
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btnperiodo'])) {
    try {
        $feini = $_POST['feini'] ?? '';
        $fefin = $_POST['fefin'] ?? '';
        $id_localidad = !empty($_POST['localidad']) ? intval($_POST['localidad']) : 0;

        // 🔢 Obtener MAX(id_periodo)
        $stmtMax = $pdo->query("SELECT MAX(id_periodo+1) as max_id FROM periodo");
        $resultado = $stmtMax->fetch(PDO::FETCH_ASSOC);
        $id_periodo = $resultado['max_id'];
        
        // Validaciones
        if (empty($feini)) throw new Exception('La fecha de inicio es obligatoria');
        if (empty($fefin)) throw new Exception('La fecha de término es obligatoria');
        if ($id_localidad <= 0) throw new Exception('La localidad es obligatoria');
        if ($feini > $fefin) throw new Exception('La fecha de inicio no puede ser mayor a la fecha de término');
        if (empty($id_periodo)) throw new Exception('No se pudo obtener el ID del período');
        
        // Insertar en base de datos
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO periodo (id_periodo, fecha_inicio, fecha_fin, id_localidad, estado) 
            VALUES (?, ?, ?, ?, 'EN PROCESO')
        ");
        $stmt->execute([$id_periodo, $feini, $fefin, $id_localidad]);
        
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
// PROCESAR FORMULARIO DE GASTO (CON IMAGEN)
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btngasto'])) {
    try {
        // ✅ 1. Recibir y sanitizar datos
        $fecha_gasto = $_POST['fecha_gasto'] ?? date('Y-m-d');
        $monto = floatval($_POST['monto_gasto'] ?? 0);
        $id_mantenedor = !empty($_POST['Mantenedor']) ? intval($_POST['Mantenedor']) : null;
        $id_tecnico = !empty($_POST['Tecnico']) ? intval($_POST['Tecnico']) : null;
        $id_periodo = !empty($_POST['Periodo']) ? intval($_POST['Periodo']) : null;
        $id_programa = !empty($_POST['Programa']) ? intval($_POST['Programa']) : null;
        $id_rubro = !empty($_POST['Rubro']) ? intval($_POST['Rubro']) : null;
        $observaciones = trim($_POST['observaciones'] ?? '');
        
        // ✅ 2. Procesar archivo subido (comprobante)
        $ruta_comprobante = null;
        
        if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['comprobante'];
            
            // Validar tamaño
            if ($archivo['size'] > $maxFileSize) {
                throw new Exception('El archivo excede el tamaño máximo de 5MB');
            }
            
            // Validar tipo MIME
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($archivo['tmp_name']);
            
            if (!in_array($mimeType, $allowedTypes)) {
                throw new Exception('Tipo de archivo no permitido. Solo JPG, PNG, GIF o PDF');
            }
            
            // Validar extensión
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) {
                throw new Exception('Extensión de archivo no permitida');
            }
            
            // Generar nombre único y seguro
            $nombreUnico = uniqid('comp_') . '_' . time() . '.' . $extension;
            $rutaDestino = $uploadDir . $nombreUnico;
            
            // Mover archivo
            if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
                throw new Exception('Error al guardar el archivo');
            }
            
            // Guardar ruta relativa en BD
            $ruta_comprobante = $rutaDestino;
        }

        // ✅ 3. Validaciones de datos
        if (empty($fecha_gasto)) throw new Exception('La fecha del gasto es obligatoria');
        if ($monto <= 0) throw new Exception('El monto debe ser mayor a 0');
        if (!$id_rubro) throw new Exception('Debe seleccionar un rubro');
        if (!$id_programa) throw new Exception('Debe seleccionar un programa');
        if (!$id_periodo) throw new Exception('Debe seleccionar un periodo');
        if (!$id_mantenedor && !$id_tecnico) {
            throw new Exception('Debe seleccionar al menos un empleado');
        }

        // ✅ 4. INSERT con comprobante
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO gasto (
                fecha_gasto, monto, descripcion, id_mantenedor, id_tecnico, 
                id_rubro, id_periodo, id_programa, comprobante, fecha_registro, estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
        ");
        
        $stmt->execute([
            $fecha_gasto,
            $monto,
            $observaciones,
            $id_mantenedor,
            $id_tecnico,
            $id_rubro,
            $id_periodo,
            $id_programa,
            $ruta_comprobante  // ← Ruta del archivo o NULL
        ]);
        
        $id_gasto = $pdo->lastInsertId();
        $pdo->commit();
        
        $_SESSION['mensaje'] = "✅ Gasto #{$id_gasto} registrado correctamente";
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
    
    <style>
        /* Estilos para upload de imagen */
        .upload-container {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            margin: 15px 0;
            transition: all 0.3s;
        }
        .upload-container:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .upload-icon {
            font-size: 40px;
            color: #667eea;
            margin-bottom: 10px;
        }
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            margin: 10px 0;
        }
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
        }
        .file-input-label {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        .file-input-label:hover {
            background: #5568d3;
        }
        .file-info {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 8px;
            display: none;
        }
        .file-info.show {
            display: block;
        }
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin: 10px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .upload-rules {
            font-size: 12px;
            color: #64748b;
            margin-top: 10px;
            text-align: left;
            background: #f1f5f9;
            padding: 10px;
            border-radius: 8px;
        }
        .upload-rules ul {
            margin: 5px 0 0 20px;
            line-height: 1.8;
        }
        .btn-remove {
            background: #ef4444;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 12px;
        }
        .btn-remove:hover {
            background: #dc2626;
        }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #334155; }
        .form-group select, .form-group input, .form-group textarea {
            width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; font-family: 'Outfit', sans-serif; transition: all 0.2s;
        }
        .form-group select:focus, .form-group input:focus, .form-group textarea:focus {
            outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .btn {
            width: 100%; padding: 14px; background: #0f172a; color: white; border: none;
            border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer;
            transition: all 0.2s; font-family: 'Outfit', sans-serif; margin-top: 10px;
        }
        .btn:hover { background: #1e293b; transform: translateY(-1px); }
        @media (max-width: 768px) { .grid-2, .grid-3 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="header">
        <div class="logo">Soluciones de Tecnología Grupo Dos</div>
        <div class="navbar">
            <span>👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href='logout.php' class="nav-item">Cerrar Sesión</a>
            <a href='registroGastos.php' class="nav-item active">Gastos</a>
            <a href='dashboard.php' class="nav-item">Dashboard</a>
        </div>
    </div>

    <!-- FORMULARIO DE PERIODO -->
    <form method="POST">          
        <div class="contenedor">
            <img src="assets/add.png" class="icon" alt="">
            <h3>Registrar un periodo de Gasto</h3>
            <div>
                <label>Fecha de salida</label>
                <input name="feini" type="date">
            </div>
            <div>
                <label>Fecha de termino</label>
                <input name="fefin" type="date">
            </div>
            <div>
                <div class="form-group">
                    <label>Localidad</label>
                    <select name="localidad" id="selectLocalidad" required>
                        <option value="">Seleccione una Localidad</option>
                        <?php foreach($localidades as $lo): ?>
                            <option value="<?php echo $lo['id_localidad']; ?>">
                                <?php echo htmlspecialchars($lo['nombre_localidad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div> 
            <button type="submit" name="btnperiodo" class="btn">+ Añadir Periodo</button>
        </div>
    </form>
    
    <!-- FORMULARIO DE GASTO CON UPLOAD -->
    <!-- ✅ IMPORTANTE: enctype="multipart/form-data" para subir archivos -->
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="contenedor">
            <img src="assets/add.png" class="icon" alt="">
            <h3>Registrar Gasto por Empleado</h3>

            <!-- Empresa -->
            <div class="form-group">
                <label>Seleccionar Empresa</label>
                <select name="Empresa" id="selectEmpresa" required>
                    <option value="">Seleccione una Empresa</option>
                    <?php foreach($grupos as $grupo): ?>
                        <option value="<?php echo $grupo['id_grupo']; ?>">
                            <?php echo htmlspecialchars($grupo['nombre_grupo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Fecha del gasto -->
            <div class="form-group">
                <label>Fecha del gasto</label>
                <input name="fecha_gasto" type="date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <!-- Empleados -->
            <h3>🔧 Seleccionar Empleado</h3>
            <div class="grid-2">
                <div class="form-group">
                    <label>Mantenedor</label>
                    <select name="Mantenedor" id="selectMantenedor">
                        <option value="">-- Seleccione Mantenedor --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Técnico</label>
                    <select name="Tecnico" id="selectTecnico">
                        <option value="">-- Seleccione Técnico --</option>
                    </select>
                </div>
            </div>

            <!-- Periodo y Programa -->
            <div class="grid-2">
                <div class="form-group">
                    <label>Periodo</label>
                    <select name="Periodo" required>
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
                    <select name="Programa" required>
                        <option value="">Seleccione un Programa</option>
                        <?php foreach($programas as $pro): ?>
                            <option value="<?php echo $pro['id_programa']; ?>">
                                <?php echo htmlspecialchars($pro['programa']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Rubro y Monto -->
            <h3>💰 Categoría de Gasto</h3>
            <div class="grid-3">
                <div class="form-group">
                    <label>Rubro</label>
                    <select name="Rubro" required>
                        <option value="">Seleccione un Rubro</option>
                        <?php foreach($rubros as $ru): ?>
                            <option value="<?php echo $ru['id_rubro']; ?>">
                                <?php echo htmlspecialchars($ru['nombre_rubro']); ?>
                            </option>
                        <?php endforeach; ?>    
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto</label>
                    <input type="number" name="monto_gasto" step="0.01" min="0.01" placeholder="$0.00" required>
                </div>
            </div>

            <!-- Observaciones -->
            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones" rows="3" placeholder="Detalles adicionales..."></textarea>
            </div>

            <!-- ✅ CAMPO DE UPLOAD DE COMPROBANTE -->
            <div class="form-group">
                <label>📎 Comprobante (Opcional)</label>
                
                <div class="upload-container" id="dropZone">
                    <div class="upload-icon">📁</div>
                    <p>Arrastra tu archivo aquí o</p>
                    
                    <div class="file-input-wrapper">
                        <input type="file" 
                               name="comprobante" 
                               id="comprobante" 
                               accept="image/*,application/pdf"
                               onchange="previewFile(this)">
                        <label for="comprobante" class="file-input-label">
                            📷 Seleccionar Archivo
                        </label>
                    </div>
                    
                    <div class="file-info" id="fileInfo">
                        <strong>Archivo seleccionado:</strong><br>
                        <span id="fileName"></span><br>
                        <small id="fileSize"></small>
                        <div id="imagePreview"></div>
                        <button type="button" class="btn-remove" onclick="removeFile()">🗑️ Eliminar</button>
                    </div>
                </div>
                
                <div class="upload-rules">
                    <strong>📋 Requisitos:</strong>
                    <ul>
                        <li>Formatos: JPG, PNG, GIF o PDF</li>
                        <li>Tamaño máximo: 5MB</li>
                        <li>El archivo debe ser legible</li>
                    </ul>
                </div>
            </div>

            <!-- Botón de envío -->
            <button type="submit" name="btngasto" class="btn">+ Añadir Gasto</button>
        </div>
    </form>

    <!-- FOOTER -->
    <footer class="footer">
        <p>© 2026 Soluciones de Tecnología Grupo Dos | Todos los derechos reservados</p>
    </footer>

    <!-- JavaScript para preview y validación de archivo -->
    <script>
        // Elementos del DOM
        const selectEmpresa = document.getElementById('selectEmpresa');
        const selectMantenedor = document.getElementById('selectMantenedor');
        const selectTecnico = document.getElementById('selectTecnico');
        const fileInput = document.getElementById('comprobante');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const imagePreview = document.getElementById('imagePreview');
        const dropZone = document.getElementById('dropZone');
        
        // Configuración
        const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
        const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

        // Preview del archivo seleccionado
        function previewFile(input) {
            const file = input.files[0];
            if (!file) return;
            
            // Validar tipo
            if (!ALLOWED_TYPES.includes(file.type)) {
                alert('❌ Tipo de archivo no permitido. Solo JPG, PNG, GIF o PDF');
                input.value = '';
                return;
            }
            
            // Validar tamaño
            if (file.size > MAX_FILE_SIZE) {
                alert('❌ El archivo excede el tamaño máximo de 5MB');
                input.value = '';
                return;
            }
            
            // Mostrar información
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.classList.add('show');
            
            // Preview para imágenes
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="preview-image">`;
                };
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                imagePreview.innerHTML = '<div style="font-size:40px">📄</div><small>Archivo PDF</small>';
            }
        }
        
        // Eliminar archivo seleccionado
        function removeFile() {
            fileInput.value = '';
            fileInfo.classList.remove('show');
            imagePreview.innerHTML = '';
        }
        
        // Formatear tamaño de archivo
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        // Drag and drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#667eea';
            dropZone.style.background = '#f0f4ff';
        });
        
        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#ddd';
            dropZone.style.background = '#f8f9fa';
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#ddd';
            dropZone.style.background = '#f8f9fa';
            
            const file = e.dataTransfer.files[0];
            if (file) {
                fileInput.files = e.dataTransfer.files;
                previewFile(fileInput);
            }
        });

        // ===== Cargar empleados según empresa (tu código existente) =====
        async function cargarMantenedores(idGrupo) {
            try {
                selectMantenedor.disabled = true;
                selectMantenedor.innerHTML = '<option value="">Cargando...</option>';
                const response = await fetch(`api/get_mantenedores.php?id_cuadrilla=${idGrupo}`);
                const result = await response.json();
                if (result.success) {
                    llenarSelect(selectMantenedor, result.data, 'id_mantenedor', 'nombre');
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        async function cargarTecnicos(idGrupo) {
            try {
                selectTecnico.disabled = true;
                selectTecnico.innerHTML = '<option value="">Cargando...</option>';
                const response = await fetch(`api/get_tecnico.php?id_cuadrilla=${idGrupo}`);
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

        selectEmpresa?.addEventListener('change', function() {
            const idGrupo = this.value;
            selectMantenedor.innerHTML = '<option value="">-- Seleccione --</option>';
            selectMantenedor.disabled = true;
            selectTecnico.innerHTML = '<option value="">-- Seleccione --</option>';
            selectTecnico.disabled = true;
            if (idGrupo) {
                Promise.all([cargarMantenedores(idGrupo), cargarTecnicos(idGrupo)]);
            }
        });
    </script>

</body>
</html>