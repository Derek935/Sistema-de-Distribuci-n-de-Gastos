<?php
session_start();
require 'conexion/conexion.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
    $stmt = $pdo->query("SELECT id_localidad,nombre_localidad FROM localidad");
    
     $periodo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->query("SELECT id_grupo,nombre_grupo FROM grupo");
    
    $grupo = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $stmt3 = $pdo->query("SELECT id_mantenedor,nombre FROM mantenedor");
    
    $mantene = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    $stmt4 = $pdo->query("SELECT id_tecnico,nombre FROM tecnico");
    
    $tecnico = $stmt4->fetchAll(PDO::FETCH_ASSOC);

    $stmt5 = $pdo->query("SELECT id_localidad,nombre_localidad FROM localidad");
    
    $loacalidad = $stmt5->fetchAll(PDO::FETCH_ASSOC);

    $stmt6 = $pdo->query("SELECT id_programa,programa FROM programa");
    
    $programa = $stmt6->fetchAll(PDO::FETCH_ASSOC);
        
    foreach($grupo as $dato) {
    echo $dato['id_grupo'];  
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
                <label>localidad</label>
                <select name="localidad" required>
                    <option value="">Seleccione una localidad</option>
                    <?php foreach($periodo as $peri): ?>
                        <option value="<?php echo $peri['id_localidad']; ?>"
                                <?php echo ($peri['id_localidad'] ) ; ?>>
                           <?php echo htmlspecialchars($peri['nombre_localidad']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

                <button type="submit" name="btnperiodo" class="btn">+ Añadir Periodo</button>
        </div>
        </form>

    <div class="contenedor">
        <img src="assets/add.png" class="icon" alt="">
        <h3>Registrar Gasto por Empleado</h3>

        <label>Seleccionar Empresa</label>
       <select name="Empresa" required>
                    <option value="">Seleccione una Empresa</option>
                    <?php foreach($grupo as $gru): ?>
                        <option value="<?php echo $gru['id_grupo']; ?>"
                                <?php echo ($gru['id_grupo'] ) ; ?>>
                           <?php echo htmlspecialchars($gru['nombre_grupo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

        <label>Filtrar por nombre o región</label>
        <input type="text" id="buscador" placeholder="Buscar empleado...">

        <h3>Seleccionar Empleado</h3>

        <h4>Mantenedor</h4>
        <div>
            <select name="Mantenedor" required>
                    <option value="">Seleccione un Mantenerdor</option>
                    <?php foreach($mantene as $mante): ?>
                        <option value="<?php echo $mante['id_mantenedor']; ?>"
                                <?php echo ($mante['id_mantenedor'] ) ; ?>>
                           <?php echo htmlspecialchars($mante['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
        </div>

        <h4>Técnico</h4>
        <div>
           <select name="Tecnico" required>
                    <option value="">Seleccione un Tecnico</option>
                    <?php foreach($tecnico as $tec): ?>
                        <option value="<?php echo $tec['id_tecnico']; ?>"
                                <?php echo ($tec['id_tecnico'] ) ; ?>>
                           <?php echo htmlspecialchars($tec['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
        </div>

        <div class="grid-2">

            <div>
                <label>Región</label>
                <select name="Region" required>
                    <option value="">Seleccione una Region</option>
                    <?php foreach($loacalidad as $loca): ?>
                        <option value="<?php echo $loca['id_localidad']; ?>"
                                <?php echo ($loca['id_localidad'] ) ; ?>>
                           <?php echo htmlspecialchars($loca['nombre_localidad']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>   

            <div>
                <label>Programa</label>
                <select name="Programa" required>
                    <option value="">Seleccione un Programa</option>
                    <?php foreach($programa as $pro): ?>
                        <option value="<?php echo $pro['id_programa']; ?>"
                                <?php echo ($pro['id_programa'] ) ; ?>>
                           <?php echo htmlspecialchars($pro['programa']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

        </div>

        <h3>Categoría de Gasto</h3>

        <div class="grid-3">

        <div class="rubro"><span>Gasolina</span><input type="number"  placeholder="$0.00"></div>
        <div class="rubro"><span>Hotel</span><input type="number"   placeholder="$0.00"></div>
        <div class="rubro"><span>Casetas</span><input type="number"   placeholder="$0.00"></div>
        <div class="rubro"><span>Materiales</span><input type="number"   placeholder="$0.00"></div>
        <div class="rubro"><span>Impuesto de Acceso de Vía</span><input type="number"   placeholder="$0.00"></div>
        <div class="rubro"><span>Viático mantenedor</span><input type="number"   placeholder="$0.00"></div>
        <div class="rubro"><span>Viático técnico</span><input type="number"   placeholder="$0.00"></div>
        <div class="rubro"><span>Recargas </span><input type="number"   placeholder="$0.00"></div>
        <div class="rubro"><span>Otros</span><input type="number"   placeholder="$0.00"></div>

        </div>

        

        <label>Observaciones</label>
        <textarea placeholder="Detalles adicionales..."></textarea>

        <button class="btn">+ Añadir Gasto</button>

    </div>

<!-- FOOTER -->
<footer class="footer">
    <p>© 2026 Soluciones de Tecnología Grupo Dos | Todos los derechos reservados</p>
</footer>


</body>

</html>
