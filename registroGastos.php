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
    <title>Sistema de Gastos</title>
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/styles.css" />
    <script src="js/app.js"></script>
</head>

<body>

    <!-- HEADER -->
    <div class="header-registro">
        <div class="logo">📄 Soluciones de Tecnología Grupo Dos</div>
        
        <div class="navbar">
            <a class="nav-item">Login</a>
            <a class="nav-item">Gastos</a>
            <a class="nav-item active">Dashboard</a>
            <div class="main-content">
       
        </div>
        </div>
    </div>
         <div class="header">
            <h1>Panel de Control</h1>
            <div class="user-info">
                <span>👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href='logout.php' class="btn-logout">Cerrar Sesión</button>
            </div>

    <div class="card">

        <h2>Registrar Gasto por Empleado</h2>

        <label>Seleccionar Empresa</label>
        <select>
            <option>Ferromex</option>
            <option>Ferrosur</option>
        </select>

        <label>Filtrar por nombre o región</label>
        <input type="text" id="buscador" placeholder="Buscar empleado...">

        <h3>Seleccionar Empleado</h3>

        <h4>Mantenedor</h4>
        <div class="lista">
            <label><input type="checkbox"> Juan Moreno - Guadalajara</label>
            <label><input type="checkbox"> Oscar González - Tepic</label>
            <label><input type="checkbox"> Rosario Valdez - Mochis</label>
        </div>

        <h4>Técnico</h4>
        <div class="lista">
            <label><input type="checkbox"> Jonathan Rodríguez - Tepic</label>
            <label><input type="checkbox"> Luis Pérez - CDMX</label>
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

        </div>

        <div class="grid-2">

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

        <div class="rubro"><span>Gasolina</span><input type="number" placeholder="$0.00"></div>
        <div class="rubro"><span>Hotel</span><input type="number" placeholder="$0.00"></div>
        <div class="rubro"><span>Casetas</span><input type="number" placeholder="$0.00"></div>
        <div class="rubro"><span>Materiales</span><input type="number" placeholder="$0.00"></div>
        <div class="rubro"><span>Accesos vía</span><input type="number" placeholder="$0.00"></div>
        <div class="rubro"><span>Viático mantenedor</span><input type="number" placeholder="$0.00"></div>
        <div class="rubro"><span>Viático técnico</span><input type="number" placeholder="$0.00"></div>
        <div class="rubro"><span>Recargas</span><input type="number" placeholder="$0.00"></div>
        <div class="rubro"><span>Otros</span><input type="number" placeholder="$0.00"></div>

        <label>Observaciones</label>
        <textarea placeholder="Detalles adicionales..."></textarea>

        <button class="btn">+ Añadir Gasto</button>

    </div>



</body>

</html>