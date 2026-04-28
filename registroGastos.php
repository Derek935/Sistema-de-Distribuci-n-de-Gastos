<?php
session_start();

require 'conexion/conexion.php';

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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btnperiodo'])) {
    
    try {
        // 1. Recibir y validar datos
        $feini = $_POST['feini'] ?? '';
        $fefin = $_POST['fefin'] ?? '';
        $id_localidad = !empty($_POST['localidad']) ? intval($_POST['localidad']) : 0;

         // 🔢 Obtener MAX(id_periodo)
        $stmtMax = $pdo->query("SELECT MAX(id_periodo+1) as max_id FROM periodo");
        $resultado = $stmtMax->fetch(PDO::FETCH_ASSOC);
        $id_periodo = $resultado['max_id'];
        
  
        // 2. Validaciones
        if (empty($feini)) {
            throw new Exception('La fecha de inicio es obligatoria');
        }
        if (empty($fefin)) {
            throw new Exception('La fecha de término es obligatoria');
        }
        if ($id_localidad <= 0) {
            throw new Exception('La localidad es obligatoria');
        }
        if ($feini > $fefin) {
            throw new Exception('La fecha de inicio no puede ser mayor a la fecha de término');
        }
         if (empty($id_periodo)) {
            throw new Exception('No se pudo obtener el ID del período');
        }
        // 3. Insertar en base de datos
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO periodo (id_periodo,fecha_inicio, fecha_fin, id_localidad, estado) 
            VALUES (?,?, ?, ?, 'EN PROCESO')
        ");

        $stmt->execute([
           $id_periodo,
            $feini,
            $fefin,
            $id_localidad
        ]);

       
       
        
        $pdo->commit();
         

        // ✅ CORRECCIÓN: Variable correcta en mensaje 
        $_SESSION['mensaje'] = "✅ Periodo registrado correctamente";
        $_SESSION['tipo_mensaje'] = 'success';

        // Redirect después de éxito
        header("Location: registroGastos.php");
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btngasto'])) {
    
    try {
        // ✅ 1. Recibir y sanitizar datos
        $fecha_gasto = $_POST['fecha_gasto'] ?? date('Y-m-d');
        $monto = floatval($_POST['monto_gasto'] ?? 0);
        
        // ❌ ELIMINAR: $id_grupo NO existe en la tabla gasto
        // $id_grupo = !empty($_POST['Empresa']) ? intval($_POST['Empresa']) : null;
        
        $id_mantenedor = !empty($_POST['Mantenedor']) ? intval($_POST['Mantenedor']) : null;
        $id_tecnico = !empty($_POST['Tecnico']) ? intval($_POST['Tecnico']) : null;
        $id_periodo = !empty($_POST['Periodo']) ? intval($_POST['Periodo']) : null;
        $id_programa = !empty($_POST['Programa']) ? intval($_POST['Programa']) : null;
        $id_rubro = !empty($_POST['Rubro']) ? intval($_POST['Rubro']) : null;
        $observaciones = trim($_POST['observaciones'] ?? '');

        // ✅ 2. Validaciones (sin $id_grupo)
        if (empty($fecha_gasto)) throw new Exception('La fecha del gasto es obligatoria');
        if ($monto <= 0) throw new Exception('El monto debe ser mayor a 0');
        // ❌ ELIMINAR validación de $id_grupo
        if (!$id_rubro) throw new Exception('Debe seleccionar un rubro');
        if (!$id_programa) throw new Exception('Debe seleccionar un programa');
        if (!$id_periodo) throw new Exception('Debe seleccionar un periodo');
        
        if (!$id_mantenedor && !$id_tecnico) {
            throw new Exception('Debe seleccionar al menos un empleado');
        }

        // ✅ 3. INSERT ajustado a la estructura REAL de la tabla
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO gasto (
                fecha_gasto, 
                monto, 
                descripcion, 
                id_mantenedor, 
                id_tecnico, 
                id_rubro, 
                id_periodo, 
                id_programa,
                comprobante, 
                fecha_registro, 
                estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW(), 1)
        ");
        
        // ✅ 8 valores que coinciden con 8 placeholders (?)
        $stmt->execute([
            $fecha_gasto,        // 1. fecha_gasto
            $monto,              // 2. monto
            $observaciones,      // 3. descripcion
            $id_mantenedor,      // 4. id_mantenedor
            $id_tecnico,         // 5. id_tecnico
            $id_rubro,           // 6. id_rubro
            $id_periodo,         // 7. id_periodo
            $id_programa         // 8. id_programa
            // 9. comprobante = NULL (literal en SQL)
            // 10. fecha_registro = NOW() (literal en SQL)
            // 11. estado = 1 (literal en SQL)
        ]);
        
        $id_gasto = $pdo->lastInsertId();
        $pdo->commit();
        
        $_SESSION['mensaje'] = "✅ Gasto #{$id_gasto} registrado correctamente";
        $_SESSION['tipo_mensaje'] = 'success';
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error PDO: " . $e->getMessage());
        $_SESSION['mensaje'] = "❌ Error BD: " . ($e->errorInfo[2] ?? $e->getMessage());
        $_SESSION['tipo_mensaje'] = 'error';
    } catch (Exception $e) {
        $_SESSION['mensaje'] = "❌ Error: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = 'error';
    }
    header("Location: registroGastos.php");
    exit();
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
    <script src="js/app.js"></script>
</head>

<body>

    <!-- HEADER -->
    <div class="header">
        <div class="logo">Soluciones de Tecnología Grupo Dos</div>
        
        <div class="navbar">
            <span>👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href='logout.php' class="nav-item">Cerrar Sesión</button>
                <a href='registroGastos.php' class="nav-item" >Gastos</a>
                <a href='dashboard.php' class="nav-item active" href='dashboard.php'>Dashboard</a>
            <div class="main-content">
       
        </div>
        </div>
    </div>

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
                <label>localidad</label>
                <select name="localidad" id="selectLocalidad" required >
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
    
<form method="POST" action="">
    <div class="contenedor">
        <img src="assets/add.png" class="icon" alt="">
        <h3> Registrar Gasto por Empleado</h3>

        <!-- Empresa -->
        <div class="form-group">
            <label> Seleccionar Empresa</label>
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
        <div>
            <label> Fecha del gasto</label>
            <!-- ✅ name único para evitar conflicto con formulario de periodo -->
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
            <div>
                <label> Periodo</label>
                <select name="Periodo" required>
                    <option value="">Seleccione un Periodo</option>
                    <?php foreach($periodo as $peri): ?>
                        <option value="<?php echo $peri['id_periodo']; ?>">
                            <?php echo htmlspecialchars($peri['id_periodo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label> Programa</label>
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
        <h3> Categoría de Gasto</h3>
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
            <!-- ✅ CORRECCIÓN: name="monto_gasto" en vez de name="rubro" -->
            <div class="form-group">
                <label>Monto</label>
                <input type="number" name="monto_gasto" step="0.01" min="0.01" placeholder="$0.00" required>
            </div>
        </div>

        <!-- ✅ CORRECCIÓN: Agregar name="observaciones" al textarea -->
        <div class="form-group">
            <label> Observaciones</label>
            <textarea name="observaciones" rows="3" placeholder="Detalles adicionales..."></textarea>
        </div>

        <!-- Botón con name="btngasto" -->
        <button type="submit" name="btngasto" class="btn">+ Añadir Gasto</button>
    </div>
</form>

<!-- FOOTER -->
<footer class="footer">
    <p>© 2026 Soluciones de Tecnología Grupo Dos | Todos los derechos reservados</p>
</footer>
<script>
        // Elementos del DOM
        const selectEmpresa = document.getElementById('selectEmpresa');
        const selectCuadrillas = document.getElementById('selectCuadrillas');
        const selectMantenedor = document.getElementById('selectMantenedor');
        const selectTecnico = document.getElementById('selectTecnico');
        const selectLocalidad = document.getElementById('selectLocalidad');
        const errorMsg = document.getElementById('errorMsg');

        // Función para mostrar errores
        function showError(message) {
            errorMsg.textContent = '❌ ' + message;
            errorMsg.style.display = 'block';
            setTimeout(() => errorMsg.style.display = 'none', 5000);
        }

        // Función para llenar un select con datos JSON
        function llenarSelect(selectElement, datos, valueField, textField, placeholder = '-- Seleccione --') {
            selectElement.innerHTML = `<option value="">${placeholder}</option>`;
            
            if (!datos || datos.length === 0) {
                selectElement.innerHTML = '<option value="">No hay datos disponibles</option>';
                selectElement.disabled = true;
                return;
            }
            
            datos.forEach(item => {
                const option = document.createElement('option');
                option.value = item[valueField];
                option.textContent = item[textField];
                selectElement.appendChild(option);
            });
            selectElement.disabled = false;
        }

        // 🔗 Cargar Mantenedores según Empresa seleccionada
        async function cargarMantenedores(idCuadrilla) {
            try {
                selectMantenedor.disabled = true;
                selectMantenedor.innerHTML = '<option value="">Cargando...</option>';
                
                const response = await fetch(`api/get_mantenedores.php?id_cuadrilla=${idCuadrilla}`);
                const result = await response.json();
                
                if (result.success) {
                    llenarSelect(selectMantenedor, result.data, 'id_mantenedor', 'nombre', '-- Seleccione Mantenedor --');
                } else {
                    showError(result.message);
                    selectMantenedor.innerHTML = '<option value="">Error al cargar</option>';
                }
            } catch (error) {
                showError('Error de conexión: ' + error.message);
                selectMantenedor.innerHTML = '<option value="">Error</option>';
            }
        }

        // 🔗 Cargar Técnicos según Empresa seleccionada
        async function cargarTecnicos(idCuadrilla) {
            try {
                selectTecnico.disabled = true;
                selectTecnico.innerHTML = '<option value="">Cargando...</option>';
                
                const response = await fetch(`api/get_tecnico.php?id_cuadrilla=${idCuadrilla}`);
                const result = await response.json();
                
                if (result.success) {
                    llenarSelect(selectTecnico, result.data, 'id_tecnico', 'nombre', '-- Seleccione Técnico --');
                } else {
                    showError(result.message);
                    selectTecnico.innerHTML = '<option value="">Error al cargar</option>';
                }
            } catch (error) {
                showError('Error de conexión: ' + error.message);
                selectTecnico.innerHTML = '<option value="">Error</option>';
            }
        }

        // 🔗 Cargar Regiones según Programa seleccionado
    

        // 🎯 Event Listener: Cambio en Empresa
        selectEmpresa.addEventListener('change', function() {
            const idGrupo = this.value;
            
            // Resetear selects dependientes
            selectMantenedor.innerHTML = '<option value="">-- Primero seleccione Empresa --</option>';
            selectMantenedor.disabled = true;
            selectTecnico.innerHTML = '<option value="">-- Primero seleccione Empresa --</option>';
            selectTecnico.disabled = true;
            
          
            if (idGrupo) {
                // Cargar ambos selects en paralelo
                Promise.all([
                    cargarMantenedores(idGrupo),
                    cargarTecnicos(idGrupo),
                 
                ]);
            }
        });


        // 🔍 Filtro de búsqueda en tiempo real
        document.getElementById('buscador').addEventListener('input', function() {
            const filtro = this.value.toLowerCase();
            
            // Filtrar mantenedores
            Array.from(selectMantenedor.options).forEach(option => {
                const texto = option.textContent.toLowerCase();
                option.style.display = texto.includes(filtro) ? '' : 'none';
            });
            
            // Filtrar técnicos
            Array.from(selectTecnico.options).forEach(option => {
                const texto = option.textContent.toLowerCase();
                option.style.display = texto.includes(filtro) ? '' : 'none';
            });


            
        });

        // ✅ Validación antes de enviar
        document.getElementById('formGasto').addEventListener('submit', function(e) {
            if (!selectEmpresa.value) {
                e.preventDefault();
                showError('Por favor seleccione una Empresa');
                selectEmpresa.focus();
            }
            if (!selectMantenedor.value && !selectTecnico.value) {
                e.preventDefault();
                showError('Por favor seleccione al menos un empleado (Mantenedor o Técnico)');
            }
            
        });
    </script>

</body>

</html>