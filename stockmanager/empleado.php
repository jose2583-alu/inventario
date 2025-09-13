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
            border: 2px solid #000000ff;
            border-radius: 5px;
        }
        
        #info-producto {
            padding: 10px;
            background: #e9ecef;
            border-radius: 5px;
            min-height: 20px;
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

        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <!-- Audio de confirmación -->
    <audio id="audio-confirm" src="assets/sounds/confirm.mp3" preload="auto" style="display:none;"></audio>
    <audio id="audio-error" src="assets/sounds/error.mp3" preload="auto" style="display:none;"></audio>
    
    <h2>Bienvenido <?php echo htmlspecialchars($emp['nombre']); ?></h2>

    <?php if (isset($_GET['ok']) && $_GET['ok'] == 1): ?>
        <div class="success-message">
            ✅ Salidas registradas exitosamente (<?php echo (int)$_GET['registrados']; ?> productos)
        </div>
    <?php endif; ?>

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

    <!-- Usa Quagga2 -->
    <script src="https://unpkg.com/@ericblade/quagga2/dist/quagga.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('=== INICIANDO ESCÁNER ===');
        
        // Obtener elementos del DOM
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
        const audioError = document.getElementById('audio-error');

        // Verificar que todos los elementos fueron encontrados
        console.log('Elementos encontrados:');
        console.log('btnScan:', !!btnScan);
        console.log('btnCerrar:', !!btnCerrar);
        console.log('btnLimpiar:', !!btnLimpiar);
        console.log('readerDiv:', !!readerDiv);
        console.log('infoDiv:', !!infoDiv);
        console.log('productosContainer:', !!productosContainer);
        console.log('productosEscaneados:', !!productosEscaneados);
        console.log('productosDataInput:', !!productosDataInput);
        console.log('btnSave:', !!btnSave);

        // Verificar que elementos críticos existan
        if (!productosContainer) {
            console.error('❌ ERROR: No se encontró el elemento productos-container');
            return;
        }
        if (!productosEscaneados) {
            console.error('❌ ERROR: No se encontró el elemento productos-escaneados');
            return;
        }

        let productosMap = new Map();
        let scanning = false;
        let lastScannedCode = '';
        let lastScannedTime = 0;
        let processingCode = false; // Bandera para evitar procesamiento múltiple

        function actualizarListaProductos() {
            console.log('Actualizando lista de productos:', productosMap);
            
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
                        <button type="button" onclick="eliminarProducto(${productoId})" 
                            style="margin-left: 5px; background: #dc3545; color: white; border: none; border-radius: 3px; padding: 2px 6px; cursor: pointer;">×</button>
                    </div>
                `;
                productosEscaneados.appendChild(div);
            });

            // Preparar datos para envío
            const productosArray = Array.from(productosMap.values()).map(data => ({
                id: data.id,
                nombre: data.nombre,
                cantidad: data.cantidad
            }));
            
            productosDataInput.value = JSON.stringify(productosArray);
            console.log('Datos preparados para envío:', productosDataInput.value);
        }

        window.eliminarProducto = function(productoId) {
            productosMap.delete(productoId);
            actualizarListaProductos();
        }

        function mostrarMensaje(mensaje, tipo = 'info') {
            infoDiv.textContent = mensaje;
            infoDiv.className = tipo === 'error' ? 'error-message' : 'success-message';
            
            // Limpiar mensaje después de 3 segundos
            setTimeout(() => {
                if (infoDiv.textContent === mensaje) {
                    infoDiv.textContent = '';
                    infoDiv.className = '';
                }
            }, 3000);
        }

        function buscarProducto(codigo) {
            console.log('=== INICIANDO BÚSQUEDA ===');
            console.log('Código a buscar:', codigo);
            console.log('ProcessingCode actual:', processingCode);
            
            if (processingCode) {
                console.log('Ya se está procesando un código, ignorando...');
                return;
            }

            processingCode = true;
            mostrarMensaje(`Buscando producto: ${codigo}...`);

            const url = 'buscar_producto_por_codigo.php?barcode=' + encodeURIComponent(codigo);
            console.log('URL de búsqueda:', url);

            fetch(url)
                .then(res => {
                    console.log('Estado de respuesta HTTP:', res.status);
                    if (!res.ok) {
                        throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                    }
                    return res.text(); // Primero obtener como texto para debugging
                })
                .then(texto => {
                    console.log('Respuesta RAW del servidor:', texto);
                    
                    let data;
                    try {
                        data = JSON.parse(texto);
                    } catch (e) {
                        console.error('Error al parsear JSON:', e);
                        throw new Error('Respuesta no es JSON válido');
                    }
                    
                    console.log('Datos parseados:', data);
                    
                    if (data.success && data.producto) {
                        const productoId = parseInt(data.producto.id);
                        const stockActual = parseInt(data.producto.cantidad) || 0;
                        const cantidadEscaneada = productosMap.has(productoId) ? productosMap.get(productoId).cantidad : 0;
                        const stockRestante = stockActual - cantidadEscaneada;

                        if (stockRestante <= 0) {
                            mostrarMensaje(`❌ Stock agotado para ${data.producto.nombre}`, 'error');
                            if (audioError) {
                                audioError.currentTime = 0;
                                audioError.play().catch(e => {});
                            }
                            return; // No sumar más
                        }

                        if (stockRestante <= 5) {
                            mostrarMensaje(`⚠️ Stock bajo (${stockRestante}) para ${data.producto.nombre}`, 'error');
                        }

                        if (productosMap.has(productoId)) {
                            // Incrementar cantidad si ya existe
                            const producto = productosMap.get(productoId);
                            producto.cantidad++;
                            productosMap.set(productoId, producto);
                            console.log('Producto actualizado:', producto);
                            mostrarMensaje(`✅ ${data.producto.nombre} - Cantidad actualizada: ${producto.cantidad}`);
                        } else {
                            // Agregar nuevo producto
                            const nuevoProducto = {
                                id: productoId,
                                nombre: data.producto.nombre,
                                codigo: codigo,
                                cantidad: 1,
                                stock: stockActual
                            };
                            productosMap.set(productoId, nuevoProducto);
                            console.log('Producto agregado:', nuevoProducto);
                            console.log('Map después de agregar:', Array.from(productosMap.entries()));
                            mostrarMensaje(`✅ ${data.producto.nombre} agregado (Stock: ${data.producto.cantidad})`);
                        }
                        
                        console.log('Llamando a actualizarListaProductos()...');
                        actualizarListaProductos();
                        
                        // Reproducir sonido de confirmación
                        if (audioConfirm) {
                            audioConfirm.currentTime = 0;
                            audioConfirm.play().catch(e => console.log('No se pudo reproducir audio'));
                        }
                        
                    } else {
                        console.log('Producto no encontrado o error:', data.message);
                        mostrarMensaje(`❌ ${data.message || 'Producto no encontrado'}: ${codigo}`, 'error');
                        if (audioError) {
                            audioError.currentTime = 0;
                            audioError.play().catch(e => console.log('No se pudo reproducir audio'));
                        }
                    }
                })
                .catch(error => {
                    console.error('Error completo en búsqueda:', error);
                    mostrarMensaje('❌ Error al buscar producto: ' + error.message, 'error');
                    if (audioError) {
                        audioError.currentTime = 0;
                        audioError.play().catch(e => console.log('No se pudo reproducir audio'));
                    }
                })
                .finally(() => {
                    console.log('Finalizando búsqueda, processingCode = false');
                    processingCode = false;
                });
        }

        btnScan.addEventListener('click', function() {
            if (scanning) return;

            if (typeof Quagga === 'undefined') {
                alert('Error: La librería de escáner no está cargada.');
                return;
            }

            btnScan.disabled = true;
            btnCerrar.style.display = 'inline-block';
            readerDiv.style.display = 'block';
            mostrarMensaje('Iniciando cámara...');
            scanning = true;

            Quagga.init({
                inputStream: {
                    name: "Live",
                    type: "LiveStream",
                    target: readerDiv,
                    constraints: {
                        facingMode: "environment",
                        width: { ideal: 640 },
                        height: { ideal: 480 }
                    },
                    singleChannel: true
                },
                locator: {
                    patchSize: "medium",
                    halfSample: true
                },
                decoder: {
                    readers: [
                        "code_128_reader",
                        "ean_reader",
                        "ean_8_reader",
                        "code_39_reader",
                        "upc_reader",
                        "upc_e_reader"
                    ]
                },
                locate: true,
                numOfWorkers: navigator.hardwareConcurrency || 2,
                frequency: 10
            }, function (err) {
                if (err) {
                    console.error("Error al iniciar Quagga:", err);
                    mostrarMensaje("❌ No se pudo acceder a la cámara", 'error');
                    cerrarEscaner();
                    return;
                }
                console.log("Quagga iniciado correctamente");
                mostrarMensaje("📷 Escaneando... Apunta al código de barras");
                Quagga.start();
            });

            Quagga.onDetected(function (data) {
                if (!data || !data.codeResult || !data.codeResult.code) {
                    return;
                }

                const codigo = data.codeResult.code.trim();
                const currentTime = Date.now();

                // Evitar códigos duplicados en poco tiempo
                if (codigo === lastScannedCode && (currentTime - lastScannedTime) < 2000) {
                    console.log('Código duplicado ignorado:', codigo);
                    return;
                }

                // Validar calidad del código
                const decodedCodes = data.codeResult.decodedCodes || [];
                const errors = decodedCodes
                    .filter(c => c.error !== undefined)
                    .map(c => c.error);

                if (errors.length > 0) {
                    const avgError = errors.reduce((a, b) => a + b, 0) / errors.length;
                    if (avgError > 0.1) {
                        console.log('Código de baja calidad ignorado:', codigo, 'Error promedio:', avgError);
                        return;
                    }
                }

                console.log('Código detectado:', codigo);
                lastScannedCode = codigo;
                lastScannedTime = currentTime;

                // Detener el escáner después de una detección exitosa
                cerrarEscaner();

                buscarProducto(codigo);
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
            processingCode = false;
            if (!productosMap.size) {
                infoDiv.textContent = '';
            }
        }

        btnCerrar.addEventListener('click', cerrarEscaner);

        btnLimpiar.addEventListener('click', function() {
            if (confirm('¿Quieres limpiar toda la lista?')) {
                productosMap.clear();
                actualizarListaProductos();
                mostrarMensaje('🗑️ Lista limpiada');
            }
        });

        salidaForm.addEventListener('submit', function(e) {
            if (productosMap.size === 0) {
                e.preventDefault();
                alert('Debes escanear al menos un producto');
                return false;
            }
            if (scanning) cerrarEscaner();
        });

        // Toggle para mostrar/ocultar productos
        const toggleBtn = document.getElementById('toggleProductos');
        const productosDiv = document.getElementById('productosContainer');
        let abierto = false;
        
        toggleBtn.addEventListener('click', function() {
            abierto = !abierto;
            productosDiv.style.display = abierto ? 'block' : 'none';
            toggleBtn.textContent = abierto ? 'Ocultar' : 'Mostrar';
        });

        // Inicializar
        actualizarListaProductos();
    });
    </script>
</body>
</html>
