<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
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
            <a class="nav-item">Login</a>
            <a class="nav-item">Gastos</a>
            <a class="nav-item active">Dashboard</a>
            <div class="main-content">
       
        </div>
        </div>
    </div>

    <div class="contenedor">
        <img src="assets/add.png" class="icon" alt="">
        <h3>Registrar Gasto por Empleado</h3>

        <label>Seleccionar Empresa</label>
        <select>
            <option>Ferromex</option>
            <option>Ferrosur</option>
        </select>

        <label>Filtrar por nombre o región</label>
        <input type="text" id="buscador" placeholder="Buscar empleado...">

        <h3>Seleccionar Empleado</h3>

        <h4>Mantenedor</h4>
        <div class="lista grid-4">
            <label><input type="checkbox" class="check" > Juan Moreno - Guadalajara</label>
            <label><input type="checkbox" class="check" > Oscar González - Tepic</label>
            <label><input type="checkbox" class="check" > Rosario Valdez - Mochis</label>
            <label><input type="checkbox" class="check" > Juan Moreno - Guadalajara</label>
            <label><input type="checkbox" class="check" > Oscar González - Tepic</label>
            <label><input type="checkbox" class="check" > Rosario Valdez - Mochis</label>
        </div>

        <h4>Técnico</h4>
        <div class="lista grid-4">
            <label><input type="checkbox" class="check" > Jonathan Rodríguez - Tepic</label>
            <label><input type="checkbox" class="check" > Luis Pérez - CDMX</label>
        </div>

        <div class="grid-2">

            <div>
                <label>Región</label>
                <select>
                    <option>Hermosillo</option>
                    <option>Puebla</option>
                </select>
            </div>

            <div>
                <label>Programa</label>
                <select>
                    <option>EALV</option>
                </select>
            </div>

            <div>
                <label>Fecha de salida</label>
                <input type="date">
            </div>

            <div>
                <label>Tipo de salida</label>
                <select>
                    <option>Fortuito</option>
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
        <div class="rubro"><span>Recargas Telefónicas</span><input type="number"   placeholder="$0.00"></div>
        <div class="rubro"><span>Otros</span><input type="number"   placeholder="$0.00"></div>

        </div>

        

        <label>Observaciones</label>
        <textarea placeholder="Detalles adicionales..."></textarea>

        <button class="btn">+ Añadir Gasto</button>

    </div>



</body>

</html>