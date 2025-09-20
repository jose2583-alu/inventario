<?php
session_start();

// CONFIGURAR ZONA HORARIA ANTES DE CUALQUIER OPERACIÓN
date_default_timezone_set('America/Mexico_City');

include "conexion.php";

// 1. Verificar sesión
if (empty($_SESSION['empleado_codigo'])) {
    header('Location: index.php');
    exit;
}

// 2. Obtener empleado
$codigoEmp = $conn->real_escape_string($_SESSION['empleado_codigo']);
$empData = $conn
    ->query("SELECT id, nombre FROM empleados WHERE codigo='$codigoEmp'")
    ->fetch_assoc();
if (!$empData) {
    die("Empleado inválido.");
}

$empleadoId = (int)$empData['id'];
$nombreEmpleado = $empData['nombre'];

// 3. Obtener datos de productos desde JSON
$productosData = isset($_POST['productos_data']) ? $_POST['productos_data'] : '';

if (empty($productosData)) {
    die("No hay productos para registrar.");
}

$productos = json_decode($productosData, true);
if (!$productos || !is_array($productos)) {
    die("Datos de productos inválidos.");
}

// 4. Obtener timestamp actual en zona horaria de México
$fechaActual = date('Y-m-d H:i:s');
error_log("Registrando movimiento en fecha/hora México: " . $fechaActual);

// 5. Procesar movimiento agrupado en transacción
$conn->begin_transaction();
try {
    $productosValidos = [];
    $totalProductos = 0;
    $totalValor = 0;
    
    // 5.1. Validar todos los productos primero
    foreach ($productos as $producto) {
        $productoId = (int)$producto['id'];
        $cantidad = (int)$producto['cantidad'];
        
        if ($productoId <= 0 || $cantidad <= 0) {
            continue; // Saltar productos inválidos
        }
        
        // Verificar stock actual
        $stmt = $conn->prepare(
            "SELECT cantidad, nombre, precio FROM productos WHERE id = ? FOR UPDATE"
        );
        $stmt->bind_param("i", $productoId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$row = $result->fetch_assoc()) {
            throw new Exception("Producto con ID $productoId no existe.");
        }
        
        $stock = (int)$row['cantidad'];
        $nombreProducto = $row['nombre'];
        $precio = (float)$row['precio'];
        $stmt->close();
        
        if ($stock < $cantidad) {
            throw new Exception("Stock insuficiente para '$nombreProducto' (disponible: $stock, solicitado: $cantidad).");
        }
        
        // Agregar a productos válidos
        $productosValidos[] = [
            'id' => $productoId,
            'nombre' => $nombreProducto,
            'cantidad' => $cantidad,
            'precio' => $precio,
            'subtotal' => $cantidad * $precio
        ];
        
        $totalProductos += $cantidad;
        $totalValor += ($cantidad * $precio);
    }
    
    if (empty($productosValidos)) {
        throw new Exception("No se encontraron productos válidos para registrar.");
    }
    
    // 5.2. Crear movimiento cabecera con fecha/hora específica de México
    $stmt = $conn->prepare(
        "INSERT INTO movimientos_cabecera (empleado_id, fecha, total_productos, total_valor) 
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("isid", $empleadoId, $fechaActual, $totalProductos, $totalValor);
    $stmt->execute();
    $movimientoId = $conn->insert_id;
    $stmt->close();
    
    if (!$movimientoId) {
        throw new Exception("Error al crear el movimiento principal.");
    }
    
    // 5.3. Procesar cada producto válido
    $productosRegistrados = 0;
    foreach ($productosValidos as $producto) {
        // Insertar detalle del movimiento
        $stmt = $conn->prepare(
            "INSERT INTO movimientos_detalle (movimiento_id, producto_id, cantidad, precio_unitario, subtotal) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iiidd", $movimientoId, $producto['id'], $producto['cantidad'], $producto['precio'], $producto['subtotal']);
        $stmt->execute();
        $stmt->close();
        
        // Actualizar stock
        $stmt = $conn->prepare(
            "UPDATE productos SET cantidad = cantidad - ? WHERE id = ?"
        );
        $stmt->bind_param("ii", $producto['cantidad'], $producto['id']);
        $stmt->execute();
        $stmt->close();
        
        $productosRegistrados++;
    }
    
    if ($productosRegistrados == 0) {
        throw new Exception("No se pudo registrar ningún producto.");
    }
    
    $conn->commit();
    
    // Log para verificar
    error_log("Movimiento registrado exitosamente con fecha México: " . $fechaActual);
    
    // Mensaje de éxito con detalles
    $mensaje = "Movimiento registrado exitosamente!\\n";
    $mensaje .= "ID Movimiento: $movimientoId\\n";
    $mensaje .= "Empleado: $nombreEmpleado\\n";
    $mensaje .= "Fecha/Hora: " . date('d/m/Y H:i:s', strtotime($fechaActual)) . "\\n";
    $mensaje .= "Productos registrados: $productosRegistrados\\n";
    $mensaje .= "Total cantidad: $totalProductos unidades\\n";
    $mensaje .= "Valor total: $" . number_format($totalValor, 2);
    
    header("Location: empleado.php?ok=1&movimiento_id=$movimientoId&registrados=$productosRegistrados&total_valor=" . urlencode($totalValor));
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error en registro de movimiento: " . $e->getMessage());
    die("Error: " . $e->getMessage());
}
?>
