<?php 
session_start();
include "conexion.php";

$codigo = $_SESSION["empleado_codigo"];
$emp = $conn->query("SELECT * FROM empleados WHERE codigo='$codigo'")->fetch_assoc();

if (!$emp) {
    die("Empleado no encontrado. Contacta al administrador.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Panel Empleado</title>
    <link rel="stylesheet" href="assets/css/empleado.css">
    <link rel="icon" href="assets/img/em.png" type="image/png">
</head>
<body>
    <h2>Bienvenido <?php echo $emp['nombre']; ?></h2>

    <h3>Registrar salida de producto</h3>
    <form method="POST" action="registrar_salida.php">
        <label>Producto:</label>
        <select name="producto_id">
            <?php
            $res = $conn->query("SELECT * FROM productos");
            while($row = $res->fetch_assoc()){
                echo "<option value='".$row['id']."'>".$row['nombre']." (Stock: ".$row['cantidad'].")</option>";
            }
            ?>
        </select><br><br>
        Cantidad: <input type="number" name="cantidad" required><br><br>
        <button type="submit">Registrar</button>
    </form>
</body>
</html>