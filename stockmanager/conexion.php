<?php
// conexion.php - conexión a la base de datos con zona horaria configurada
// Configurar zona horaria ANTES de cualquier otra cosa
date_default_timezone_set('America/Mexico_City');

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

// CONFIGURAR ZONA HORARIA EN MYSQL
// Esto es MUY IMPORTANTE: configura la zona horaria de la sesión MySQL
$conn->query("SET time_zone = '-06:00'");

// Compatibilidad: crear alias $conexion si otras partes del proyecto lo usan
$conexion = $conn;

// Función para obtener timestamp actual en zona horaria de México
function obtener_timestamp_mexico() {
    return date('Y-m-d H:i:s');
}

// Función para formatear fechas para mostrar
function formatear_fecha_mexico($fecha_mysql) {
    return date('d/m/Y H:i', strtotime($fecha_mysql));
}
?>
