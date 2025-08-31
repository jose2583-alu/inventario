<?php
session_start();
include "conexion.php";

// 1. Verificar sesión
if (empty($_SESSION['empleado_codigo'])) {
    header('Location: index.php');
    exit;
}

// 2. Obtener empleado
$codigoEmp = $conn->real_escape_string($_SESSION['empleado_codigo']);
$empData = $conn
    ->query("SELECT id FROM empleados WHERE codigo='$codigoEmp'")
    ->fetch_assoc();
if (!$empData) {
    die("Empleado inválido.");
}

$empleadoId = (int)$empData['id'];

// 3. Obtener datos de productos desde JSON
$productosData = isset($_POST['productos_data']) ? $_POST['productos_data'] : '';

if (empty($productosData)) {
    die("No hay productos para registrar.");
}

$productos = json_decode($productosData, true);
if (!$productos || !is_array($productos)) {
    die("Datos de productos inválidos.");
}

// 4. Procesar cada producto en transacción
$conn->begin_transaction();
try {
    $movimientosRegistrados = 0;
    
    foreach ($productos as $producto) {
        $productoId = (int)$producto['id'];
        $cantidad = (int)$producto['cantidad'];
        
        if ($productoId <= 0 || $cantidad <= 0) {
            continue; // Saltar productos inválidos
        }
        
        // 4.1. Verificar stock actual
        $stmt = $conn->prepare(
            "SELECT cantidad, nombre FROM productos WHERE id = ? FOR UPDATE"
        );
        $stmt->bind_param("i", $productoId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$row = $result->fetch_assoc()) {
            throw new Exception("Producto con ID $productoId no existe.");
        }
        
        $stock = (int)$row['cantidad'];
        $nombreProducto = $row['nombre'];
        $stmt->close();
        
        if ($stock < $cantidad) {
            throw new Exception("Stock insuficiente para '$nombreProducto' (disponible: $stock, solicitado: $cantidad).");
        }
        
        // 4.2. Insertar movimiento
        $stmt = $conn->prepare(
            "INSERT INTO movimientos (producto_id, empleado_id, cantidad, fecha) 
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->bind_param("iii", $productoId, $empleadoId, $cantidad);
        $stmt->execute();
        $stmt->close();
        
        // 4.3. Actualizar stock
        $stmt = $conn->prepare(
            "UPDATE productos SET cantidad = cantidad - ? WHERE id = ?"
        );
        $stmt->bind_param("ii", $cantidad, $productoId);
        $stmt->execute();
        $stmt->close();
        
        $movimientosRegistrados++;
    }
    
    if ($movimientosRegistrados == 0) {
        throw new Exception("No se pudo registrar ningún producto.");
    }
    
    $conn->commit();
    header("Location: empleado.php?ok=1&registrados=$movimientosRegistrados");
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    die("Error: " . $e->getMessage());
}
?>
