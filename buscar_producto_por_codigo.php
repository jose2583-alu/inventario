<?php
header('Content-Type: application/json');
include "conexion.php";

if (empty($_GET['barcode'])) {
    echo json_encode(['success'=>false]);
    exit;
}

$barcode = $conn->real_escape_string($_GET['barcode']);
$sql = "SELECT id, nombre, cantidad 
        FROM productos 
        WHERE codigo_barras='$barcode'
        LIMIT 1";
$res = $conn->query($sql);

if ($prod = $res->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'producto' => $prod
    ]);
} else {
    echo json_encode(['success'=>false]);
}
