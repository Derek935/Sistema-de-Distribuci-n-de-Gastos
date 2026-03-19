<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Gastos</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<header class="header">
    <div class="logo">
        <span class="icon">📄</span>
        <span class="title">Soluciones de Tecnología Grupo Dos</span>
    </div>

    <nav class="navbar">
        <a href="#" class="nav-item">Login</a>
        <a href="#" class="nav-item">Gastos</a>
        <a href="#" class="nav-item active">Dashboard</a>
    </nav>
</header>

<?php include("conexion.php"); ?>

<div class="card">

    <h2>Registrar Gasto por Empleado</h2>

    <!-- Seleccionar empresa -->
    <label>Seleccionar Empresa</label>
    <select name="empresa" id="empresa">
        <?php
        $query = "SELECT * FROM empresas";
        $result = mysqli_query($conexion, $query);

        while($row = mysqli_fetch_assoc($result)){
            echo "<option value='".$row['id']."'>".$row['nombre']."</option>";
        }
        ?>
    </select>

    <!-- Filtro -->
    <label>Filtrar por nombre o región</label>
    <input type="text" id="buscador" placeholder="Buscar empleado...">

    <!-- Seleccionar empleado -->
    <h3>Seleccionar Empleado</h3>

    <!-- MANTENEDORES -->
    <h4>Mantenedor</h4>
    <div class="lista" id="lista-mantenedor">

        <?php
        $query = "SELECT * FROM empleados WHERE tipo='mantenedor'";
        $result = mysqli_query($conexion, $query);

        while($row = mysqli_fetch_assoc($result)){
            echo "
            <label class='item'>
                <input type='checkbox' class='empleado' data-nombre='".$row['nombre']."' value='".$row['id']."'>
                ".$row['nombre']." - ".$row['region']."
            </label>";
        }
        ?>

    </div>

    <!-- TECNICOS -->
    <h4>Técnico</h4>
    <div class="lista" id="lista-tecnico">

        <?php
        $query = "SELECT * FROM empleados WHERE tipo='tecnico'";
        $result = mysqli_query($conexion, $query);

        while($row = mysqli_fetch_assoc($result)){
            echo "
            <label class='item'>
                <input type='checkbox' class='empleado' data-nombre='".$row['nombre']."' value='".$row['id']."'>
                ".$row['nombre']." - ".$row['region']."
            </label>";
        }
        ?>

    </div>

</div>

<div class="card">

    <!-- FILA SUPERIOR -->
    <div class="grid-2">

        <!-- Región -->
        <div>
            <label>Región que atiende</label>
            <select name="region">
                <?php
                $query = "SELECT * FROM regiones";
                $result = mysqli_query($conexion, $query);

                while($row = mysqli_fetch_assoc($result)){
                    echo "<option value='".$row['id']."'>".$row['nombre']."</option>";
                }
                ?>
            </select>
        </div>

        <!-- Programa -->
        <div>
            <label>Programa</label>
            <select name="programa">
                <?php
                $query = "SELECT * FROM programas";
                $result = mysqli_query($conexion, $query);

                while($row = mysqli_fetch_assoc($result)){
                    echo "<option value='".$row['id']."'>".$row['nombre']."</option>";
                }
                ?>
            </select>
        </div>

    </div>

    <div class="grid-2">

        <!-- Fecha -->
        <div>
            <label>Fecha de salida</label>
            <input type="date" name="fecha_salida">
        </div>

        <!-- Tipo de salida -->
        <div>
            <label>Tipo de salida</label>
            <select name="tipo_salida">
                <?php
                $query = "SELECT * FROM tipos_salida";
                $result = mysqli_query($conexion, $query);

                while($row = mysqli_fetch_assoc($result)){
                    echo "<option value='".$row['id']."'>".$row['nombre']."</option>";
                }
                ?>
            </select>
        </div>

    </div>

    <!-- CATEGORÍAS -->
    <h3>Categoría de Gasto y Cantidad</h3>

    <div class="categorias">

        <?php
        $rubros = [
            "gasolina",
            "hotel",
            "casetas",
            "materiales",
            "accesos_via",
            "viatico_mantenedor",
            "viatico_tecnico",
            "recargas",
            "otros"
        ];

        foreach($rubros as $rubro){
            echo "
            <div class='rubro'>
                <label>".ucwords(str_replace("_"," ",$rubro))."</label>
                <input type='number' step='0.01' min='0' name='$rubro' placeholder='$0.00'>
            </div>";
        }
        ?>

    </div>

</div>

<!-- OBSERVACIONES -->
<div class="observaciones">
    <label>Observaciones</label>
    <textarea name="observaciones" placeholder="Detalles adicionales..."></textarea>
</div>

<!-- BOTÓN -->
<div class="acciones">
    <button type="submit" class="btn-guardar">
        + Añadir Gasto
    </button>
</div>

</body>
</html>