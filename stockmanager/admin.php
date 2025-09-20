<?php
// admin.php - versión corregida
session_start();
date_default_timezone_set('America/Mexico_City');
// En desarrollo puedes activar errores (quítalo en producción)
// ini_set('display_errors', 1); error_reporting(E_ALL);

include("conexion.php"); // Debe definir $conn como mysqli

// === Funciones utilitarias ===
function calcularDigitoVerificadorEAN13($codigo) {
    $codigo = str_pad(substr(preg_replace('/[^0-9]/','', $codigo), 0, 12), 12, "0", STR_PAD_LEFT);
    $suma = 0;
    for ($i = 0; $i < 12; $i++) {
        $digito = intval($codigo[$i]);
        $suma += ($i % 2 == 0) ? $digito : $digito * 3;
    }
    $digitoVerificador = (10 - ($suma % 10)) % 10;
    return $codigo . $digitoVerificador;
}

function generar_codigo_base_12() {
    $codigo = '';
    for ($i = 0; $i < 12; $i++) {
        $codigo .= mt_rand(0, 9);
    }
    return $codigo;
}

function set_success($msg) {
    $_SESSION['msg_success'] = $msg;
}
function set_error($msg) {
    $_SESSION['msg_error'] = $msg;
}

// === Registrar nuevo empleado ===
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['registrar_empleado'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    if ($nombre === '') {
        set_error("Nombre requerido.");
        header("Location: admin.php"); exit;
    }

    // Generar código único de 4 dígitos
    $stmtCheck = $conn->prepare("SELECT id FROM empleados WHERE codigo = ?");
    do {
        $codigo = str_pad(mt_rand(0, 9999), 4, "0", STR_PAD_LEFT);
        $stmtCheck->bind_param("s", $codigo);
        $stmtCheck->execute();
        $stmtCheck->store_result();
    } while ($stmtCheck->num_rows > 0);
    $stmtCheck->close();

    $stmt = $conn->prepare("INSERT INTO empleados (codigo, nombre) VALUES (?, ?)");
    $stmt->bind_param("ss", $codigo, $nombre);
    if ($stmt->execute()) {
        set_success("Empleado registrado con éxito. Código: <b>$codigo</b>");
        $stmt->close();
        header("Location: admin.php"); exit;
    } else {
        $err = $stmt->error;
        $stmt->close();
        set_error("Error al registrar empleado: $err");
        header("Location: admin.php"); exit;
    }
}

// === Eliminar empleado ===
if (isset($_GET['eliminar_empleado'])) {
    $id = intval($_GET['eliminar_empleado']);
    $stmt = $conn->prepare("DELETE FROM empleados WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        set_success("Empleado eliminado.");
        $stmt->close();
        header("Location: admin.php"); exit;
    } else {
        $err = $stmt->error; $stmt->close();
        set_error("Error al eliminar empleado: $err");
        header("Location: admin.php"); exit;
    }
}

// === Eliminar producto ===
if (isset($_GET['eliminar_producto'])) {
    $id = intval($_GET['eliminar_producto']);
    $stmt = $conn->prepare("DELETE FROM productos WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        set_success("Producto eliminado.");
        $stmt->close();
        header("Location: admin.php"); exit;
    } else {
        $err = $stmt->error; $stmt->close();
        set_error("Error al eliminar producto: $err");
        header("Location: admin.php"); exit;
    }
}

// === Editar empleado ===
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['editar_empleado'])) {
    $id = intval($_POST['id']);
    $nombre = trim($_POST['nombre'] ?? '');
    if ($nombre === '') {
        set_error("Nombre requerido.");
        header("Location: admin.php"); exit;
    }
    $stmt = $conn->prepare("UPDATE empleados SET nombre = ? WHERE id = ?");
    $stmt->bind_param("si", $nombre, $id);
    if ($stmt->execute()) {
        set_success("Empleado actualizado.");
        $stmt->close();
        header("Location: admin.php"); exit;
    } else {
        $err = $stmt->error; $stmt->close();
        set_error("Error al actualizar empleado: $err");
        header("Location: admin.php"); exit;
    }
}

// === Editar producto ===
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['editar_producto'])) {
    $id = intval($_POST['id']);
    $nombre = trim($_POST['nombre'] ?? '');
    $codigo = preg_replace('/[^0-9]/', '', $_POST['codigo_barras'] ?? '');
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $precio = floatval($_POST['precio'] ?? 0.0);

    if ($nombre === '' || ($codigo === '')) {
        set_error("Nombre y código requeridos.");
        header("Location: admin.php"); exit;
    }

    // Si tiene 12 dígitos, calcular el dígito verificador; si tiene 13 validar
    if (strlen($codigo) == 12) {
        $codigo = calcularDigitoVerificadorEAN13($codigo);
    } elseif (strlen($codigo) == 13) {
        $base = substr($codigo, 0, 12);
        $calc = calcularDigitoVerificadorEAN13($base);
        if ($codigo !== $calc) {
            // Reemplazamos por el correcto y notificamos (no abortamos)
            $codigo = $calc;
            // Puedes usar set_error o set_success para notificar, aquí se usa success con nota
            set_success("Código corregido automáticamente a: $codigo");
        }
    } else {
        set_error("El código debe tener 12 o 13 dígitos.");
        header("Location: admin.php"); exit;
    }

    $stmt = $conn->prepare("UPDATE productos SET nombre = ?, codigo_barras = ?, cantidad = ?, precio = ? WHERE id = ?");
    $stmt->bind_param("ssidi", $nombre, $codigo, $cantidad, $precio, $id);
    if ($stmt->execute()) {
        set_success("Producto actualizado.");
        $stmt->close();
        header("Location: admin.php"); exit;
    } else {
        $err = $stmt->error; $stmt->close();
        set_error("Error al actualizar producto: $err");
        header("Location: admin.php"); exit;
    }
}

// === Agregar producto ===
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['agregar_producto'])) {
    $nombre = trim(mysqli_real_escape_string($conn, $_POST["nombre"] ?? ''));
    $codigo = preg_replace('/[^0-9]/', '', $_POST["codigo"] ?? '');
    $cantidad = intval($_POST["cantidad"] ?? 0);
    $precio = floatval($_POST["precio"] ?? 0.0);

    if ($nombre === '') {
        set_error("Nombre del producto requerido.");
        header("Location: admin.php"); exit;
    }

    // Generar código automáticamente si está vacío
    if (empty($codigo)) {
        do {
            $codigoBase = generar_codigo_base_12();
            $codigoGenerado = calcularDigitoVerificadorEAN13($codigoBase);
            // verificar existencia
            $stmtCheck = $conn->prepare("SELECT id FROM productos WHERE codigo_barras = ?");
            $stmtCheck->bind_param("s", $codigoGenerado);
            $stmtCheck->execute();
            $stmtCheck->store_result();
            $exists = $stmtCheck->num_rows > 0;
            $stmtCheck->close();
        } while ($exists);
        $codigo = $codigoGenerado;
    } else {
        // Validar longitud
        if (strlen($codigo) == 12) {
            $codigo = calcularDigitoVerificadorEAN13($codigo);
        } elseif (strlen($codigo) == 13) {
            $codigoBase = substr($codigo, 0, 12);
            $codigoCalculado = calcularDigitoVerificadorEAN13($codigoBase);
            if ($codigo !== $codigoCalculado) {
                // reemplazar por código correcto
                $codigo = $codigoCalculado;
            }
        } else {
            set_error("Error: El código debe tener 12 o 13 dígitos.");
            header("Location: admin.php"); exit;
        }

        // Verificar que el código no exista
        $stmtCheck = $conn->prepare("SELECT id FROM productos WHERE codigo_barras = ?");
        $stmtCheck->bind_param("s", $codigo);
        $stmtCheck->execute();
        $stmtCheck->store_result();
        if ($stmtCheck->num_rows > 0) {
            $stmtCheck->close();
            set_error("Error: El código de barras ya existe.");
            header("Location: admin.php"); exit;
        }
        $stmtCheck->close();
    }

    // Insertar con prepared statement
    $stmt = $conn->prepare("INSERT INTO productos (nombre, codigo_barras, cantidad, precio) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssid", $nombre, $codigo, $cantidad, $precio);
    if ($stmt->execute()) {
        set_success("Producto agregado con éxito. Código EAN-13 válido: <b>$codigo</b>");
        $stmt->close();
        header("Location: admin.php"); exit;
    } else {
        $err = $stmt->error; $stmt->close();
        set_error("Error al agregar producto: $err");
        header("Location: admin.php"); exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Panel de Administrador</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/agregar_producto.css">
    <link rel="icon" href="assets/img/ad.png" type="image/png">
    <style>
        /* (estilos tal cual) */
        .container { max-width: 1200px; margin:0 auto; padding:20px; }
        .section { background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:20px; margin-bottom:30px; }
        table { width:100%; border-collapse: collapse; margin-top:15px; }
        table th, table td { border:1px solid #ddd; padding:8px; text-align:left; }
        table th { background:#f2f2f2; font-weight:bold; }
        .btn{ padding:6px 12px; border:none; border-radius:4px; cursor:pointer; margin:2px; display:inline-block; font-size:12px; text-decoration:none; }
        .btn-primary{ background:#007bff; color:#fff; } .btn-success{ background:#28a745; color:#fff; } .btn-danger{ background:#dc3545; color:#fff; } .btn-info{ background:#17a2b8; color:#fff; } .btn-barcode{ background:#6f42c1; color:#fff; }
        .badge { padding:4px 8px; border-radius:12px; font-size:11px; font-weight:bold; }
        .badge-success{ background:#28a745; color:#fff; } .badge-warning{ background:#ffc107; color:#000; } .badge-danger{ background:#dc3545; color:#fff; }
        .barcode-preview{ max-width:200px; max-height:100px; border:1px solid #ddd; border-radius:4px; margin-top:5px; display:none; }
        .detalle-movimiento{ background:#f8f9fa; border-left:4px solid #17a2b8; }
    </style>
</head>
<body>
<div class="container">
    <h1>Panel de Administrador</h1>

    <!-- Mensajes -->
    <?php
    if (!empty($_SESSION['msg_success'])) {
        echo '<p style="color:green;">' . $_SESSION['msg_success'] . '</p>';
        unset($_SESSION['msg_success']);
    }
    if (!empty($_SESSION['msg_error'])) {
        echo '<p style="color:red;">' . $_SESSION['msg_error'] . '</p>';
        unset($_SESSION['msg_error']);
    }
    ?>

    <!-- Registrar empleado -->
    <div class="section">
        <h2>Registrar Empleado</h2>
        <form method="POST">
            <input type="text" name="nombre" placeholder="Nombre completo" required style="padding:8px; margin-right:10px;">
            <button type="submit" name="registrar_empleado" class="btn btn-primary">Registrar</button>
        </form>
    </div>

    <!-- Lista de empleados -->
    <div class="section">
        <h2>Lista de Empleados</h2>
        <table>
            <thead>
                <tr><th>ID</th><th>Código</th><th>Nombre</th><th class="actions-column">Acciones</th></tr>
            </thead>
            <tbody>
            <?php
            $resEmpl = $conn->query("SELECT * FROM empleados ORDER BY id DESC");
            while ($row = $resEmpl->fetch_assoc()) {
                $id = intval($row['id']);
                $codigo = htmlspecialchars($row['codigo'], ENT_QUOTES, 'UTF-8');
                $nombre = htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8');
                echo "<tr>
                        <td data-label='ID'>{$id}</td>
                        <td data-label='Código'><strong>{$codigo}</strong></td>
                        <td data-label='Nombre'>{$nombre}</td>
                        <td data-label='Acciones' class='actions-column'>
                            <form method='POST' class='form-inline' style='display:inline-block;'>
                                <input type='hidden' name='id' value='{$id}'>
                                <input type='text' name='nombre' value='{$nombre}' required style='width:150px;'>
                                <button type='submit' name='editar_empleado' class='btn btn-success'>Guardar</button>
                            </form>
                            <a href='admin.php?eliminar_empleado={$id}' onclick='return confirm(\"¿Eliminar empleado?\")' class='btn btn-danger'>Eliminar</a>
                        </td>
                      </tr>";
            }
            ?>
            </tbody>
        </table>
    </div>

    <!-- Agregar producto -->
    <div class="section">
        <h2>Agregar Producto</h2>
        <form method="POST">
            <div style="margin-bottom:15px;">
                <input type="text" name="nombre" placeholder="Nombre del producto" required style="padding:8px; width:250px; margin-right:10px;">
                <input type="text" name="codigo" placeholder="Código EAN-13 (12 o 13 dígitos)" maxlength="13" style="padding:8px; width:220px; margin-right:10px;">
                <input type="number" name="cantidad" placeholder="Cantidad inicial" required style="padding:8px; width:150px; margin-right:10px;">
                <input type="number" name="precio" step="0.01" min="0" placeholder="Precio" required style="padding:8px; width:120px; margin-right:10px;">
                <button type="submit" name="agregar_producto" class="btn btn-primary">Guardar</button>
            </div>
            <small style="color:#666;">Puedes ingresar 12 o 13 dígitos. Si ingresas 12, se calculará automáticamente el dígito verificador. Si no ingresas nada, se generará un código EAN-13 completo.</small>
        </form>
    </div>

    <!-- Lista de productos (colapsable) -->
    <div class="section">
        <h2 style="display:inline-block;">Lista de Productos</h2>
        <button id="toggleProductos" class="btn btn-info" style="float:right; margin-top:-8px;">Mostrar</button>
        <div id="productosContainer" style="display:none; margin-top:20px;">
            <table>
                <thead>
                    <tr><th>ID</th><th>Nombre</th><th>Código de Barras EAN-13</th><th>Cantidad</th><th>Precio</th><th class="actions-column">Acciones</th></tr>
                </thead>
                <tbody>
                    <?php
                    $resProd = $conn->query("SELECT * FROM productos ORDER BY id DESC");
                    while ($row = $resProd->fetch_assoc()) {
                        $id = intval($row['id']);
                        $nombre = htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8');
                        $codigo_barras = htmlspecialchars($row['codigo_barras'], ENT_QUOTES, 'UTF-8');
                        $cantidad = intval($row['cantidad']);
                        $precio = number_format(floatval($row['precio']), 2, ".", "");
                        $badgeClass = $cantidad > 5 ? 'badge-success' : ($cantidad > 0 ? 'badge-warning' : 'badge-danger');

                        echo "<tr>
                                <td data-label='ID'>{$id}</td>
                                <td data-label='Nombre'>{$nombre}</td>
                                <td data-label='Código'><strong>{$codigo_barras}</strong></td>
                                <td data-label='Cantidad'><span class='badge {$badgeClass}'>{$cantidad}</span></td>
                                <td data-label='Precio'>$".number_format($precio,2)."</td>
                                <td data-label='Acciones' class='actions-column'>
                                    <form method='POST' class='form-inline' style='margin-bottom:5px; display:inline-block;'>
                                        <input type='hidden' name='id' value='{$id}'>
                                        <input type='text' name='nombre' value=\"{$nombre}\" required style='width:120px; margin-right:5px;'>
                                        <input type='text' name='codigo_barras' value=\"{$codigo_barras}\" required maxlength='13' style='width:110px; margin-right:5px;'>
                                        <input type='number' name='cantidad' value='{$cantidad}' required style='width:60px; margin-right:5px;'>
                                        <input type='number' name='precio' value='{$precio}' step='0.01' min='0' required style='width:80px; margin-right:5px;'>
                                        <button type='submit' name='editar_producto' class='btn btn-success'>Guardar</button>
                                    </form>
                                    <div style='display:inline-flex; gap:5px; align-items:center;'>
                                        <a href='admin.php?eliminar_producto={$id}' onclick='return confirm(\"¿Eliminar producto?\")' class='btn btn-danger'>Eliminar</a>
                                        <button onclick='previewBarcode(\"{$codigo_barras}\", \"".addslashes($nombre)."\", this, \"{$precio}\")' class='btn btn-info'>Ver Código</button>
                                        <a href='generar_codigo_barras.php?descargar=1&codigo={$codigo_barras}&nombre=".urlencode($nombre)."&precio={$precio}' class='btn btn-barcode'>📥 Descargar PNG</a>
                                    </div>
                                    <img class='barcode-preview' id='preview-{$id}' alt='Código de barras'>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div style="clear:both;"></div>
    </div>

    <!-- Movimientos Agrupados con reporte mensual -->
    <div class="section">
        <h2 style="display:inline-block;">Movimientos Agrupados</h2>
        <button id="toggleMovimientos" class="btn btn-info" style="float:right; margin-top:-8px;">Mostrar</button>
        <div style="clear:both;"></div>

        <!-- REPORTE MENSUAL PDF -->
        <div style="margin:15px 0; padding:15px; border:2px dashed #007bff; background:#f8f9ff; border-radius:8px;">
            <h3 style="margin:0 0 10px 0; color:#007bff;">📄 Reporte Mensual (Descargar PDF)</h3>
            <form id="reporteForm" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <label style="font-weight:bold;">
                    Mes:
                    <input type="month" id="mesReporte" name="mes" value="<?php echo date('Y-m'); ?>" style="padding:8px; margin-left:5px; border:1px solid #ccc; border-radius:4px;">
                </label>
                <button type="button" id="btnGenerarPdf" class="btn btn-primary" style="padding:8px 15px;">📥 Generar PDF del mes</button>
                <small style="color:#666; margin-left:10px;">El PDF incluirá todos los movimientos del mes seleccionado y el total vendido.</small>
            </form>
        </div>

        <div id="movimientosContainer" style="display:none; margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>ID Movimiento</th><th>Empleado</th><th>Fecha y Hora</th><th>Total Productos</th><th>Total Cobrado</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $tableCheck = $conn->query("SHOW TABLES LIKE 'movimientos_cabecera'");
                    if ($tableCheck && $tableCheck->num_rows > 0) {
                        $sql = "SELECT mc.id, e.nombre AS empleado, mc.fecha, mc.total_productos, mc.total_valor
                                FROM movimientos_cabecera mc
                                LEFT JOIN empleados e ON mc.empleado_id = e.id
                                ORDER BY mc.id DESC";
                    } else {
                        echo "<tr><td colspan='6' style='text-align:center; padding:20px; background:#fff3cd; border:2px solid #ffeaa7;'>
                                <strong>⚠️ Base de datos no actualizada</strong><br>Para usar los movimientos agrupados, ejecuta el script SQL de actualización.
                              </td></tr>";
                        $sql = "SELECT 1 WHERE 0";
                    }
                    $resMov = $conn->query($sql);
                    while ($row = $resMov->fetch_assoc()) {
                        $id = intval($row['id']);
                        $empleado = htmlspecialchars($row['empleado'] ?: '-', ENT_QUOTES, 'UTF-8');
                        $fecha = htmlspecialchars($row['fecha'], ENT_QUOTES, 'UTF-8');
                        $total_productos = intval($row['total_productos']);
                        $total_valor = number_format(floatval($row['total_valor']),2);
                        echo "<tr>
                                <td data-label='ID'><strong>#{$id}</strong></td>
                                <td data-label='Empleado'>{$empleado}</td>
                                <td data-label='Fecha y Hora'>{$fecha}</td>
                                <td data-label='Total Productos' style='text-align:center;'>{$total_productos} unidades</td>
                                <td data-label='Total Cobrado' style='font-weight:bold; color:green;'>$ {$total_valor}</td>
                                <td data-label='Acciones'><button onclick='verDetalleMovimiento({$id})' class='btn btn-info btn-sm'>Ver Detalle</button></td>
                              </tr>";
                        echo "<tr id='detalle-{$id}' style='display:none;' class='detalle-movimiento'>
                                <td colspan='6'>
                                    <div style='padding:15px;'>
                                        <h4>📋 Detalle del Movimiento #{$id}</h4>
                                        <div id='detalle-contenido-{$id}'>
                                            <div style='text-align:center; padding:20px;'>
                                                Cargando detalles...
                                            </div>
                                        </div>
                                    </div>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div style="clear:both;"></div>
    </div>

    <!-- Estadísticas -->
    <div class="section">
        <h2>Estadísticas</h2>
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
            <?php
            $totalEmpleados = intval($conn->query("SELECT COUNT(*) as total FROM empleados")->fetch_assoc()['total']);
            $totalProductos = intval($conn->query("SELECT COUNT(*) as total FROM productos")->fetch_assoc()['total']);
            $productosStockBajo = intval($conn->query("SELECT COUNT(*) as total FROM productos WHERE cantidad <= 5")->fetch_assoc()['total']);

            $tableCheck = $conn->query("SHOW TABLES LIKE 'movimientos_cabecera'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $totalMovimientos = intval($conn->query("SELECT COUNT(*) as total FROM movimientos_cabecera")->fetch_assoc()['total']);
                $ventasHoy = floatval($conn->query("SELECT COALESCE(SUM(total_valor),0) as total FROM movimientos_cabecera WHERE DATE(fecha) = CURDATE()")->fetch_assoc()['total']);
            } else {
                $totalMovimientos = intval($conn->query("SELECT COUNT(*) as total FROM movimientos")->fetch_assoc()['total']);
                $ventasHoy = 0.0;
            }
            ?>
            <div style="background:#007bff; color:white; padding:15px; border-radius:8px; text-align:center; min-width:120px;">
                <h3 style="margin:0; font-size:24px;"><?php echo $totalEmpleados; ?></h3>
                <p style="margin:5px 0 0 0;">Empleados</p>
            </div>
            <div style="background:#28a745; color:white; padding:15px; border-radius:8px; text-align:center; min-width:120px;">
                <h3 style="margin:0; font-size:24px;"><?php echo $totalProductos; ?></h3>
                <p style="margin:5px 0 0 0;">Productos</p>
            </div>
            <div style="background:#dc3545; color:white; padding:15px; border-radius:8px; text-align:center; min-width:120px;">
                <h3 style="margin:0; font-size:24px;"><?php echo $productosStockBajo; ?></h3>
                <p style="margin:5px 0 0 0;">Stock Bajo</p>
            </div>
            <div style="background:#6c757d; color:white; padding:15px; border-radius:8px; text-align:center; min-width:120px;">
                <h3 style="margin:0; font-size:24px;"><?php echo $totalMovimientos; ?></h3>
                <p style="margin:5px 0 0 0;">Movimientos</p>
            </div>
            <div style="background:#17a2b8; color:white; padding:15px; border-radius:8px; text-align:center; min-width:120px;">
                <h3 style="margin:0; font-size:18px;">$<?php echo number_format($ventasHoy,2); ?></h3>
                <p style="margin:5px 0 0 0;">Ventas Hoy</p>
            </div>
        </div>
    </div>

</div>

<!-- SCRIPTS -->
<script>
// Función para mostrar preview del código de barras
function previewBarcode(codigo, nombre, button, precio) {
    const row = button.closest('tr');
    const preview = row.querySelector('.barcode-preview');
    if (!preview) return;
    if (preview.style.display === 'block') {
        preview.style.display = 'none';
        button.textContent = 'Ver Código';
    } else {
        preview.src = 'generar_codigo_barras.php?mostrar=1&codigo=' + encodeURIComponent(codigo) + '&nombre=' + encodeURIComponent(nombre) + '&precio=' + encodeURIComponent(precio);
        preview.style.display = 'block';
        button.textContent = 'Ocultar';
    }
}

// Una sola definición de verDetalleMovimiento
function verDetalleMovimiento(movimientoId) {
    const detalleRow = document.getElementById('detalle-' + movimientoId);
    const contenido = document.getElementById('detalle-contenido-' + movimientoId);
    if (!detalleRow || !contenido) return;

    if (detalleRow.style.display === 'none' || detalleRow.style.display === '') {
        detalleRow.style.display = 'table-row';
        fetch('obtener_detalle_movimiento.php?id=' + movimientoId)
            .then(r => r.text())
            .then(data => contenido.innerHTML = data)
            .catch(() => contenido.innerHTML = '<p style="color:red;">Error al cargar los detalles</p>');
    } else {
        detalleRow.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Confirmaciones ya definidas con atributos, pero agregamos como respaldo:
    document.querySelectorAll('a[onclick*="confirm"]').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('¿Estás seguro?')) e.preventDefault();
        });
    });

    // Validación para códigos: solo números y máximo 13
    document.querySelectorAll('input[name="codigo"], input[name="codigo_barras"]').forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 13) this.value = this.value.slice(0,13);
        });
    });

    // Toggle productos
    const toggleBtn = document.getElementById('toggleProductos');
    const productosDiv = document.getElementById('productosContainer');
    let abierto = false;
    if (toggleBtn && productosDiv) {
        toggleBtn.addEventListener('click', function() {
            abierto = !abierto;
            productosDiv.style.display = abierto ? 'block' : 'none';
            toggleBtn.textContent = abierto ? 'Ocultar' : 'Mostrar';
        });
        productosDiv.style.display = 'none';
        toggleBtn.textContent = 'Mostrar';
    }

    // Toggle movimientos
    const toggleMovBtn = document.getElementById('toggleMovimientos');
    const movDiv = document.getElementById('movimientosContainer');
    let movAbierto = false;
    if (toggleMovBtn && movDiv) {
        toggleMovBtn.addEventListener('click', function() {
            movAbierto = !movAbierto;
            movDiv.style.display = movAbierto ? 'block' : 'none';
            toggleMovBtn.textContent = movAbierto ? 'Ocultar' : 'Mostrar';
        });
        movDiv.style.display = 'none';
        toggleMovBtn.textContent = 'Mostrar';
    }
});
</script>

<!-- Librerías externas (UNA sola vez, fuera de otros <script>) -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

<!-- Script para generar PDF del reporte mensual -->
<script>
document.getElementById('btnGenerarPdf').addEventListener('click', function(){
    const mes = document.getElementById('mesReporte').value; // formato YYYY-MM
    if (!mes) { alert('Selecciona un mes válido.'); return; }

    const btn = this;
    const original = btn.innerHTML;
    btn.innerHTML = '⏳ Generando PDF...';
    btn.disabled = true;

    fetch('generar_reporte_mes.php?mes=' + encodeURIComponent(mes))
    .then(res => {
        if (!res.ok) throw new Error('Error al generar el reporte');
        return res.text();
    })
    .then(html => {
        const wrapper = document.createElement('div');
        wrapper.style.position = 'fixed';
        wrapper.style.left = '-9999px';
        wrapper.style.top = '0';
        wrapper.style.background = 'white';
        wrapper.style.width = '210mm';
        wrapper.style.padding = '10px';
        wrapper.innerHTML = html;
        document.body.appendChild(wrapper);

        setTimeout(() => {
            html2canvas(wrapper, { scale: 2, useCORS: true, allowTaint: false, backgroundColor: '#ffffff' })
            .then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p','mm','a4');
                const pdfWidth = pdf.internal.pageSize.getWidth() - 20;
                const pageHeight = pdf.internal.pageSize.getHeight() - 20;
                const imgProps = { width: canvas.width, height: canvas.height };
                const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

                if (pdfHeight <= pageHeight) {
                    pdf.addImage(imgData, 'PNG', 10, 10, pdfWidth, pdfHeight);
                } else {
                    // dividir en páginas
                    const imgWidthPx = canvas.width;
                    const imgHeightPx = canvas.height;
                    const pxPerPage = Math.floor(imgWidthPx * (pageHeight / pdfWidth));
                    let position = 0;
                    let pageCount = 0;
                    while (position < imgHeightPx) {
                        const tmpCanvas = document.createElement('canvas');
                        tmpCanvas.width = imgWidthPx;
                        tmpCanvas.height = Math.min(pxPerPage, imgHeightPx - position);
                        const tmpCtx = tmpCanvas.getContext('2d');
                        tmpCtx.drawImage(canvas, 0, position, imgWidthPx, tmpCanvas.height, 0, 0, imgWidthPx, tmpCanvas.height);
                        const tmpImgData = tmpCanvas.toDataURL('image/png');
                        if (pageCount > 0) pdf.addPage();
                        const tmpHeight = (tmpCanvas.height * pdfWidth) / tmpCanvas.width;
                        pdf.addImage(tmpImgData, 'PNG', 10, 10, pdfWidth, tmpHeight);
                        position += tmpCanvas.height;
                        pageCount++;
                    }
                }

                const parts = mes.split('-'); const yyyy = parts[0], mm = parts[1];
                const lastDay = new Date(yyyy, mm, 0).getDate();
                const filename = `Reporte_${mm}_${yyyy}_del_1_al_${lastDay}.pdf`;
                pdf.save(filename);

                document.body.removeChild(wrapper);
                btn.innerHTML = original; btn.disabled = false;
                alert('✅ PDF generado: ' + filename);
            })
            .catch(err => {
                console.error(err);
                document.body.removeChild(wrapper);
                btn.innerHTML = original; btn.disabled = false;
                alert('Error al generar PDF: ' + err.message);
            });
        }, 400);
    })
    .catch(err => {
        console.error(err);
        btn.innerHTML = original; btn.disabled = false;
        alert('Error: ' + err.message);
    });
});
</script>
</body>
</html>
