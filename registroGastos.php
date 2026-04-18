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

        // 3. Insertar en base de datos
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO periodo (fecha_inicio, fecha_fin, id_localidad, estado) 
            VALUES (?, ?, ?, 'EN PROCESO')
        ");

        $stmt->execute([
            $feini,
            $fefin,
            $id_localidad
        ]);

        // ✅ CORRECCIÓN: lastInsertId() (no lastIsertId)
        $id_periodo = $pdo->lastInsertId();
        
        $pdo->commit();

        // ✅ CORRECCIÓN: Variable correcta en mensaje
        $_SESSION['mensaje'] = "✅ Periodo #{$id_periodo} registrado correctamente";
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

    <div class="contenedor">
        <img src="assets/add.png" class="icon" alt="">
        <h3>Registrar Gasto por Empleado</h3>

        <div class="form-group">
        <label>Seleccionar Empresa</label>
        <select name="Empresa"  id="selectEmpresa" required>
            <option value="">Seleccione una Empresa</option>
                        <?php foreach($grupos as $grupo): ?>
                            <option value="<?php echo $grupo['id_grupo']; ?>">
                                <?php echo htmlspecialchars($grupo['nombre_grupo']); ?>
                            </option>
                        <?php endforeach; ?>
                        </div>

       

        

        <div>
                <label>Fecha del gasto</label>
                <input name="feini" type="date">
        </div>

         <!-- <div class="form-group">
            <label>Seleccionar Cuadrilla</label>
            <select name="Cuadrilla" id="selectCuadrillas" required disabled>
                <option value="">-- Primero seleccione Cuadrilla --</option>
            </select>
        </div> -->

        <h3>Seleccionar Empleado</h3>

       <div class="grid-2">
                <div class="form-group">
                    <label>Mantenedor</label>
                    <select name="Mantenedor" id="selectMantenedor" required disabled>
                        <option value="">-- Primero seleccione Empresa --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Técnico</label>
                    <select name="Tecnico" id="selectTecnico" required disabled>
                        <option value="">-- Primero seleccione Empresa --</option>
                    </select>
                </div>
            </div>

        <div class="grid-2">


            <div>
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

        <h3>Categoría de Gasto</h3>

        <div class="grid-3">

            <label>Rubro</label>

                <select name="Rubro" required>
                    <option value="">Seleccione un Rubro</option>
                    <?php foreach($rubros as $ru): ?>
                        <option value="<?php echo $ru['id_rubro']; ?>">
                           <?php echo htmlspecialchars($ru['nombre_rubro']); ?>
                        </option>
                    <?php endforeach; ?>    
                </select>
                <input type="number" name="rubro"  placeholder="$0.00">

        </div>

        

        <label>Observaciones</label>
        <textarea placeholder="Detalles adicionales..."></textarea>

        <button class="btn">+ Añadir Gasto</button>

    </div>

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