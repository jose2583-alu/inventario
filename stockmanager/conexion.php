<?php
// conexion.php - conexión a la base de datos (crea $conn usado por el resto de archivos)
// Incluye configuracion con $server, $user, $pass, $bd
if (!isset($server)) {
    @include_once __DIR__ . '/configuracion.php';
} else {
    // configuracion.php ya fue incluida
}

// Intentar crear $conn (mysqli)
$conn = new mysqli($server, $user, $pass, $bd);

if ($conn->connect_errno) {
    // En desarrollo es útil ver el error. En producción hay que registrar el error en lugar de mostrarlo.
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    die('Falló la conexión a la base de datos: (' . $conn->connect_errno . ') ' . $conn->connect_error);
}

// Usar utf8mb4 por compatibilidad con emojis y más caracteres
$conn->set_charset('utf8mb4');

// Compatibilidad: crear alias $conexion si otras partes del proyecto lo usan
$conexion = $conn;
?>