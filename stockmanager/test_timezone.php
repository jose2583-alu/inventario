<?php
date_default_timezone_set('America/Mexico_City');
echo "Zona horaria configurada: " . date_default_timezone_get() . "<br>";
echo "Fecha y hora actual: " . date('Y-m-d H:i:s T') . "<br>";
echo "Fecha para base de datos: " . date('Y-m-d H:i:s') . "<br>";
?>
