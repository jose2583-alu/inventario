<?php
session_start();
include "conexion.php";

$codigo = $_SESSION["empleado_codigo"];
$emp = $conn->query("SELECT * FROM empleados WHERE codigo='$codigo'")->fetch_assoc();

$producto_id = $_POST["producto_id"];
$cantidad = $_POST["cantidad"];

$conn->query("INSERT INTO movimientos (producto_id, empleado_id, cantidad) 
              VALUES ('$producto_id','".$emp['id']."','$cantidad')");

$conn->query("UPDATE productos SET cantidad = cantidad - $cantidad WHERE id=$producto_id");

header("Location: empleado.php");
?>