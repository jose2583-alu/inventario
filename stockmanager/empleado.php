<?php
session_start();
include "conexion.php";

if (empty($_SESSION['empleado_codigo'])) {
    header('Location: login.php');
    exit;
}

$codigoEmp = $conn->real_escape_string($_SESSION['empleado_codigo']);
$emp = $conn
    ->query("SELECT id, nombre FROM empleados WHERE codigo='$codigoEmp'")
    ->fetch_assoc();
if (!$emp) {
    die("Empleado no encontrado. Contacta al administrador.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Panel Empleado</title>
    <link rel="stylesheet" href="assets/css/empleado.css">
    <!-- Incluir QuaggaJS desde CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
    <style>
        .producto-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        
        .producto-nombre {
            font-weight: bold;
        }
        
        .producto-cantidad {
            font-weight: bold;
            color: #007bff;
        }
        
        .btn-group {
            margin: 10px 0;
        }
        
        .btn-group button {
            margin: 5px;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        #btn-scan {
            background: #28a745;
            color: white;
        }
        
        .btn-cerrar {
            background: #dc3545;
            color: white;
        }
        
        .btn-limpiar {
            background: #ffc107;
            color: black;
        }
        
        #btn-registrar {
            background: #007bff;
            color: white;
            font-size: 16px;
            padding: 10px 20px;
        }
        
        #btn-registrar:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        #reader {
            border: 2px solid #007bff;
            border-radius: 5px;
        }
        
        #info-producto {
            padding: 10px;
            background: #e9ecef;
            border-radius: 5px;
        }

        /* Estilos para la tabla de productos */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #f2f2f2;
        }
        
        .section {
            max-width: 600px;
            margin: 30px auto;
        }
        
        #toggleProductos {
            float: right;
            margin-top: -8px;
            background: #17a2b8;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 6px 12px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Audio de confirmación -->
    <audio id="audio-confirm" src="assets/sounds/confirm.mp3" preload="auto" style="display:none;"></audio>
    <h2>Bienvenido <?php echo htmlspecialchars($emp['nombre']); ?></h2>

    <h3>Registrar salida de producto</h3>
    <form id="salida-form" method="POST" action="registrar_salida.php">
        <div class="btn-group">
            <button type="button" id="btn-scan">Escanear código de barras</button>
            <button type="button" id="btn-cerrar" class="btn-cerrar" style="display:none;">Cerrar escáner</button>
        </div>
        <div id="reader" style="width:400px; height:300px; margin: 10px 0; display:none;"></div>
        <div id="info-producto" style="margin: 10px 0; font-weight: bold;"></div>

        <!-- Lista de productos escaneados -->
        <div id="productos-container" style="display:none;">
            <h4>Productos escaneados:</h4>
            <div id="productos-escaneados"></div>
            <div class="btn-group">
                <button type="button" id="btn-limpiar" class="btn-limpiar">Limpiar lista</button>
            </div>
        </div>

        <!-- Campos ocultos para enviar datos -->
        <input type="hidden" name="productos_data" id="productos_data">

        <div class="btn-group">
            <button type="submit" id="btn-registrar" disabled>Registrar salidas</button>
        </div>
    </form>

    <!-- Lista de productos (solo vista, no editable) -->
    <div class="section" style="max-width:600px; margin:30px auto;">
        <h3 style="display:inline-block;">Lista de Productos</h3>
        <button id="toggleProductos" style="float:right; margin-top:-8px; background:#17a2b8; color:white; border:none; border-radius:5px; padding:6px 12px; cursor:pointer;">Mostrar/Ocultar</button>
        <div id="productosContainer" style="display:none; margin-top:20px;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="background:#f2f2f2; padding:8px;">Nombre</th>
                        <th style="background:#f2f2f2; padding:8px;">Cantidad</th>
                        <th style="background:#f2f2f2; padding:8px;">Precio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = mysqli_query($conn, "SELECT nombre, cantidad, precio FROM productos ORDER BY nombre ASC");
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>
                                <td style='padding:8px;'>".htmlspecialchars($row['nombre'])."</td>
                                <td style='padding:8px; text-align:center;'>".$row['cantidad']."</td>
                                <td style='padding:8px; text-align:right;'>$".number_format($row['precio'],2)."</td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div style="clear:both;"></div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Obtener referencias a los elementos del DOM
        const btnScan = document.getElementById('btn-scan');
        const btnCerrar = document.getElementById('btn-cerrar');
        const btnLimpiar = document.getElementById('btn-limpiar');
        const readerDiv = document.getElementById('reader');
        const infoDiv = document.getElementById('info-producto');
        const productosContainer = document.getElementById('productos-container');
        const productosEscaneados = document.getElementById('productos-escaneados');
        const productosDataInput = document.getElementById('productos_data');
        const btnSave = document.getElementById('btn-registrar');
        const salidaForm = document.getElementById('salida-form');
        const audioConfirm = document.getElementById('audio-confirm');

        // Verificar que todos los elementos existen
        if (!btnScan || !btnCerrar || !btnLimpiar || !readerDiv || !infoDiv || 
            !productosContainer || !productosEscaneados || !productosDataInput || 
            !btnSave || !salidaForm) {
            console.error('Algunos elementos del DOM no se encontraron');
            return;
        }

        let productosMap = new Map(); // Para guardar productos escaneados
        let scanning = false;

        function actualizarListaProductos() {
            productosEscaneados.innerHTML = '';
            
            if (productosMap.size === 0) {
                productosContainer.style.display = 'none';
                btnSave.disabled = true;
                productosDataInput.value = '';
                return;
            }

            productosContainer.style.display = 'block';
            btnSave.disabled = false;

            productosMap.forEach((data, productoId) => {
                const div = document.createElement('div');
                div.className = 'producto-item';
                div.innerHTML = `
                    <div>
                        <span class="producto-nombre">${data.nombre}</span><br>
                        <small>Código: ${data.codigo}</small>
                    </div>
                    <div>
                        <span class="producto-cantidad">${data.cantidad}x</span>
                        <button type="button" onclick="eliminarProducto(${productoId})" style="margin-left: 5px; background: #dc3545; color: white; border: none; border-radius: 3px; padding: 2px 6px; cursor: pointer;">×</button>
                    </div>
                `;
                productosEscaneados.appendChild(div);
            });

            // Actualizar campo oculto con datos JSON
            const productosArray = Array.from(productosMap.entries()).map(([id, data]) => ({
                id: id,
                nombre: data.nombre,
                cantidad: data.cantidad
            }));
            productosDataInput.value = JSON.stringify(productosArray);
        }

        // Función global para eliminar productos
        window.eliminarProducto = function(productoId) {
            productosMap.delete(productoId);
            actualizarListaProductos();
        }

        // Event listener para el botón de escanear
        btnScan.addEventListener('click', function() {
            if (scanning) return;
            
            // Verificar si Quagga está disponible
            if (typeof Quagga === 'undefined') {
                alert('Error: La librería de escáner no está cargada. Recarga la página e inténtalo de nuevo.');
                return;
            }
            
            btnScan.disabled = true;
            btnCerrar.style.display = 'inline-block';
            readerDiv.style.display = 'block';
            infoDiv.textContent = 'Iniciando cámara...';
            scanning = true;

            Quagga.init({
                inputStream: {
                    name: "Live",
                    type: "LiveStream",
                    target: readerDiv,
                    constraints: {
                        facingMode: "environment",
                        width: { min: 640, ideal: 1280, max: 1920 },
                        height: { min: 480, ideal: 720, max: 1080 }
                    }
                },
                decoder: {
                    readers: [
                        "code_128_reader", 
                        "ean_reader", 
                        "ean_8_reader",
                        "code_39_reader",
                        "code_39_vin_reader",
                        "codabar_reader",
                        "upc_reader",
                        "upc_e_reader"
                    ]
                },
                locate: true,
                locator: {
                    patchSize: "medium",
                    halfSample: true
                },
                numOfWorkers: 2,
                frequency: 10,
                debug: {
                    drawBoundingBox: true,
                    showFrequency: true,
                    drawScanline: true,
                    showPattern: true
                }
            }, function (err) {
                if (err) {
                    console.error("Error al iniciar Quagga:", err);
                    infoDiv.textContent = "No se pudo acceder a la cámara. Verifica los permisos.";
                    cerrarEscaner();
                    return;
                }
                console.log("Quagga iniciado correctamente");
                infoDiv.textContent = "Escaneando... Apunta al código de barras";
                Quagga.start();
            });

            // Event listener para códigos detectados
            Quagga.onDetected(function (data) {
                const codigo = data.codeResult.code;
                console.log("Código detectado:", codigo);
                
                infoDiv.textContent = `Código detectado: ${codigo}. Buscando producto...`;

                // Buscar producto por código
                fetch('buscar_producto_por_codigo.php?barcode=' + encodeURIComponent(codigo))
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const productoId = parseInt(data.producto.id);
                        
                        if (productosMap.has(productoId)) {
                            // Si ya existe, incrementar cantidad
                            productosMap.get(productoId).cantidad++;
                        } else {
                            // Si no existe, agregarlo
                            productosMap.set(productoId, {
                                nombre: data.producto.nombre,
                                codigo: codigo,
                                cantidad: 1,
                                stock: data.producto.cantidad
                            });
                        }
                        
                        actualizarListaProductos();
                        infoDiv.textContent = `✓ ${data.producto.nombre} agregado (Stock disponible: ${data.producto.cantidad})`;
                        // Reproducir sonido de confirmación
                        if (audioConfirm) {
                            audioConfirm.currentTime = 0;
                            audioConfirm.play();
                        }
                        
                        // Continuar escaneando sin cerrar
                    } else {
                        infoDiv.textContent = `✗ Producto no encontrado: ${codigo}`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    infoDiv.textContent = '✗ Error al buscar producto.';
                });
            });
        });

        function cerrarEscaner() {
            if (scanning) {
                Quagga.stop();
                scanning = false;
            }
            readerDiv.style.display = 'none';
            btnScan.disabled = false;
            btnCerrar.style.display = 'none';
            infoDiv.textContent = '';
        }

        // Event listener para cerrar escáner
        btnCerrar.addEventListener('click', cerrarEscaner);

        // Event listener para limpiar lista
        btnLimpiar.addEventListener('click', function() {
            if (confirm('¿Estás seguro de que quieres limpiar toda la lista?')) {
                productosMap.clear();
                actualizarListaProductos();
                infoDiv.textContent = 'Lista limpiada';
            }
        });

        // Manejar envío del formulario
        salidaForm.addEventListener('submit', function(e) {
            if (productosMap.size === 0) {
                e.preventDefault();
                alert('Debes escanear al menos un producto');
                return false;
            }
            
            if (scanning) {
                cerrarEscaner();
            }
        });

        // Colapsar/expandir lista de productos
        const toggleBtn = document.getElementById('toggleProductos');
        const productosDiv = document.getElementById('productosContainer');
        let abierto = false;
        toggleBtn.addEventListener('click', function() {
            abierto = !abierto;
            productosDiv.style.display = abierto ? 'block' : 'none';
            toggleBtn.textContent = abierto ? 'Ocultar' : 'Mostrar';
        });
        // Por defecto, oculto
        productosDiv.style.display = 'none';
        toggleBtn.textContent = 'Mostrar';

        // Inicializar estado
        actualizarListaProductos();
    });
    </script>
</body>
</html>
