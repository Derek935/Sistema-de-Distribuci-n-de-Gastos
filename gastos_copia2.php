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

<style>
body{font-family:Arial;background:#f5f6f8;margin:0;}

.header-registro{
display:flex;
justify-content:space-between;
align-items:center;
padding:20px;
background:white;
border-bottom:1px solid #ddd;
}

.logo{font-size:20px;font-weight:bold;}

.navbar{display:flex;align-items:center;gap:10px;}

.nav-item{
background:#e5e7eb;
padding:8px 15px;
border-radius:20px;
cursor:pointer;
text-decoration:none;
color:black;
}

.active{background:#020617;color:white;}

.contenedor{width:95%;margin:auto;margin-top:20px;}

.card{
background:white;
padding:20px;
border-radius:10px;
margin-bottom:20px;
}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:15px;}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;}

input, select{
width:100%;
padding:6px;
border-radius:6px;
border:1px solid #ccc;
}

textarea{
width:100%;
height:70px;
border-radius:6px;
border:1px solid #ccc;
}

.rubro{
background:#f9fafb;
padding:10px;
border-radius:8px;
display:flex;
flex-direction:column;
}

.rubro span{font-weight:bold;margin-bottom:5px;}
.rubro-input{height:35px;}

.btn{
width:100%;
padding:12px;
background:#020617;
color:white;
border:none;
border-radius:8px;
cursor:pointer;
}
</style>

</head>

<body>

<div class="header-registro">
<div class="logo">📄 Soluciones de Tecnología Grupo Dos</div>

<div class="navbar">
<span>👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
<a href='logout.php' class="nav-item">Cerrar Sesión</a>
<a class="nav-item">Login</a>
<a class="nav-item">Gastos</a>
<a class="nav-item active">Dashboard</a>
</div>
</div>

<div class="contenedor">

<!-- EMPRESA -->
<div class="card">
<label>Seleccionar Empresa</label>
<select>
<option>Ferromex</option>
<option>Ferrosur</option>
</select>

<br><br>

<label>Filtrar por nombre o región</label>
<input type="text" placeholder="Buscar empleado...">
</div>

<!-- EMPLEADOS -->
<div class="card">
<h3>Seleccionar Empleado</h3>

<h4>Mantenedor</h4>
<select>
<option value="">Seleccionar mantenedor</option>
</select>

<br><br>

<h4>Técnico</h4>
<select>
<option value="">Seleccionar técnico</option>
</select>

</div>

<!-- INFORMACIÓN DE SALIDA -->
<div class="card">
<h3>Información de salida</h3>

<div class="grid-2">

<div>
<label>Región</label>
<select>
<option value="">Seleccionar región</option>
</select>
</div>

<div>
<label>Programa</label>
<select>
<option>EALV</option>
<option>Repetidoras</option>
</select>
</div>

<div>
<label>Fecha de salida</label>
<input type="date">
</div>

<div>
<label>Tipo de salida</label>
<select>
<option>Mantenimiento</option>
<option>Falla</option>
<option>Fortuito</option>
<option>Instalación</option>
</select>
</div>

</div>
</div>

<!-- GASTOS -->
<div class="card">
<h3>Categoría de Gasto</h3>

<div class="grid-3">
<div class="rubro"><span>Gasolina</span><input type="number" class="rubro-input"></div>
<div class="rubro"><span>Hotel</span><input type="number" class="rubro-input"></div>
<div class="rubro"><span>Casetas</span><input type="number" class="rubro-input"></div>
<div class="rubro"><span>Materiales</span><input type="number" class="rubro-input"></div>
<div class="rubro"><span>Accesos vía</span><input type="number" class="rubro-input"></div>
<div class="rubro"><span>Viático mantenedor</span><input type="number" class="rubro-input"></div>
<div class="rubro"><span>Viático técnico</span><input type="number" class="rubro-input"></div>
<div class="rubro"><span>Recargas</span><input type="number" class="rubro-input"></div>
<div class="rubro"><span>Otros</span><input type="number" class="rubro-input"></div>
</div>

<br>

<h3>Total de gastos: $<span id="totalGastos">0</span></h3>

<br>

<label>Observaciones</label>
<textarea placeholder="Detalles adicionales..."></textarea>

<br><br>

<button class="btn">+ Añadir Gasto</button>

</div>

</div>

<script>
function calcularTotal(){
let total=0;

document.querySelectorAll(".rubro-input").forEach(input=>{
let val=parseFloat(input.value);
if(!isNaN(val)){ total+=val; }
});

document.getElementById("totalGastos").innerText = total.toFixed(2);
}

document.querySelectorAll(".rubro-input").forEach(input=>{
input.addEventListener("input", calcularTotal);
});
</script>

</body>
</html>
