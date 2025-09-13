<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Función para enviar respuesta JSON y terminar
function sendResponse($success, $message = '', $producto = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'producto' => $producto,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    echo json_encode($response);
    exit;
}

// Función para log de errores
function logError($message) {
    error_log("[buscar_producto_por_codigo.php] " . date('Y-m-d H:i:s') . " - " . $message);
}

try {
    // Iniciar sesión para verificar autenticación
    session_start();
    
    // Verificar que el archivo de conexión exista
    if (!file_exists('conexion.php')) {
        logError('Archivo conexion.php no encontrado');
        sendResponse(false, 'Error de configuración del servidor');
    }
    
    include "conexion.php";

    // Verificar que la conexión se estableció
    if (!isset($conn) || !$conn) {
        logError('No se pudo establecer conexión a la base de datos');
        sendResponse(false, 'Error de conexión a la base de datos');
    }

    // Verificar que el usuario esté autenticado
    if (empty($_SESSION['empleado_codigo'])) {
        logError('Intento de acceso sin sesión válida');
        sendResponse(false, 'Sesión no válida');
    }

    // Verificar que se recibió el código de barras
    if (!isset($_GET['barcode']) || empty(trim($_GET['barcode']))) {
        logError('Código de barras no proporcionado');
        sendResponse(false, 'Código de barras no proporcionado');
    }

    $barcode = trim($_GET['barcode']);
    logError("Buscando código: $barcode");

    // Validar longitud del código
    if (strlen($barcode) < 4 || strlen($barcode) > 50) {
        logError("Código con longitud inválida: " . strlen($barcode));
        sendResponse(false, 'Código de barras con formato inválido');
    }

    // Limpiar el código de barras (permitir más caracteres comunes en códigos de barras)
    $original_barcode = $barcode;
    $barcode = preg_replace('/[^a-zA-Z0-9\-_]/', '', $barcode);

    if (empty($barcode)) {
        logError("Código limpiado resultó vacío. Original: $original_barcode");
        sendResponse(false, 'Código de barras contiene caracteres inválidos');
    }

    // Escapar el código para la consulta usando prepared statements (más seguro)
    $stmt = $conn->prepare("SELECT id, nombre, cantidad, precio, codigo_barras FROM productos WHERE codigo_barras = ? LIMIT 1");
    
    if (!$stmt) {
        logError('Error preparando consulta: ' . $conn->error);
        sendResponse(false, 'Error en la consulta de base de datos');
    }

    $stmt->bind_param("s", $barcode);
    
    if (!$stmt->execute()) {
        logError('Error ejecutando consulta: ' . $stmt->error);
        sendResponse(false, 'Error ejecutando consulta de base de datos');
    }

    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $producto = $result->fetch_assoc();
        
        // Validar que el producto tenga datos completos
        if (empty($producto['id']) || empty($producto['nombre'])) {
            logError('Producto encontrado pero con datos incompletos: ' . json_encode($producto));
            sendResponse(false, 'Producto encontrado pero con datos incompletos');
        }
        
        // Convertir tipos de datos
        $producto['id'] = (int)$producto['id'];
        $producto['cantidad'] = (int)$producto['cantidad'];
        $producto['precio'] = (float)$producto['precio'];
        
        logError("Producto encontrado - ID: {$producto['id']}, Nombre: {$producto['nombre']}, Stock: {$producto['cantidad']}");
        
        // No verificar stock aquí, dejar que el empleado decida
        sendResponse(true, 'Producto encontrado', $producto);
        
    } else {
        // Si no se encuentra, intentar búsqueda por ID si es numérico
        if (is_numeric($barcode)) {
            $stmt2 = $conn->prepare("SELECT id, nombre, cantidad, precio, codigo_barras FROM productos WHERE id = ? LIMIT 1");
            
            if ($stmt2) {
                $barcode_int = (int)$barcode;
                $stmt2->bind_param("i", $barcode_int);
                
                if ($stmt2->execute()) {
                    $result2 = $stmt2->get_result();
                    
                    if ($result2->num_rows > 0) {
                        $producto = $result2->fetch_assoc();
                        
                        $producto['id'] = (int)$producto['id'];
                        $producto['cantidad'] = (int)$producto['cantidad'];
                        $producto['precio'] = (float)$producto['precio'];
                        
                        logError("Producto encontrado por ID - ID: {$producto['id']}, Código escaneado: $original_barcode");
                        sendResponse(true, 'Producto encontrado por ID', $producto);
                    }
                }
                $stmt2->close();
            }
        }
        
        // Log del código no encontrado
        logError("Código de barras no encontrado: $original_barcode (limpiado: $barcode)");
        sendResponse(false, "Producto no encontrado: $original_barcode");
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    logError("Excepción: " . $e->getMessage());
    sendResponse(false, 'Error interno del servidor');
} catch (Error $e) {
    logError("Error PHP: " . $e->getMessage());
    sendResponse(false, 'Error de configuración del servidor');
}

// Cerrar conexión si existe
if (isset($conn) && $conn) {
    $conn->close();
}
?>