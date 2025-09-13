<?php
include("conexion.php");

// Función para calcular el dígito verificador EAN-13
function calcularDigitoVerificadorEAN13($codigo) {
    // Asegurar que el código tenga exactamente 12 dígitos
    $codigo = str_pad(substr($codigo, 0, 12), 12, "0", STR_PAD_LEFT);
    
    $suma = 0;
    for ($i = 0; $i < 12; $i++) {
        $digito = intval($codigo[$i]);
        // Multiplicar por 1 las posiciones impares y por 3 las pares
        $suma += ($i % 2 == 0) ? $digito : $digito * 3;
    }
    
    $digitoVerificador = (10 - ($suma % 10)) % 10;
    return $codigo . $digitoVerificador;
}

// === Registrar nuevo empleado ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registrar_empleado'])) {
    $nombre = $_POST['nombre'];

    // Generar código de 4 dígitos aleatorio único
    do {
        $codigo = str_pad(rand(0, 9999), 4, "0", STR_PAD_LEFT);
        $check = $conn->query("SELECT id FROM empleados WHERE codigo='$codigo'");
    } while ($check->num_rows > 0);

    $sql = "INSERT INTO empleados (codigo, nombre) VALUES ('$codigo', '$nombre')";
    if (mysqli_query($conn, $sql)) {
        echo "<p style='color:green;'>Empleado registrado con éxito. Código: <b>$codigo</b></p>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// === Eliminar empleado ===
if (isset($_GET['eliminar_empleado'])) {
    $id = intval($_GET['eliminar_empleado']);
    $sql = "DELETE FROM empleados WHERE id=$id";
    if (mysqli_query($conn, $sql)) {
        header("Location: admin.php");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// === Eliminar producto ===
if (isset($_GET['eliminar_producto'])) {
    $id = intval($_GET['eliminar_producto']);
    $sql = "DELETE FROM productos WHERE id=$id";
    if (mysqli_query($conn, $sql)) {
        header("Location: admin.php");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// === Editar empleado ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['editar_empleado'])) {
    $id = intval($_POST['id']);
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);

    $sql = "UPDATE empleados SET nombre='$nombre' WHERE id=$id";
    if (mysqli_query($conn, $sql)) {
        header("Location: admin.php");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// === Editar producto ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['editar_producto'])) {
    $id = intval($_POST['id']);
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
    $codigo = mysqli_real_escape_string($conn, $_POST['codigo_barras']);
    $cantidad = intval($_POST['cantidad']);
    $precio = floatval($_POST['precio']);

    $sql = "UPDATE productos SET nombre='$nombre', codigo_barras='$codigo', cantidad=$cantidad, precio=$precio WHERE id=$id";
    if (mysqli_query($conn, $sql)) {
        header("Location: admin.php");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// === Agregar producto ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['agregar_producto'])) {
    $nombre = mysqli_real_escape_string($conn, $_POST["nombre"]);
    $codigo = $_POST["codigo"];
    $cantidad = intval($_POST["cantidad"]);
    $precio = floatval($_POST["precio"]);
    
    // Generar código automáticamente si está vacío
    if (empty($codigo)) {
        do {
            // Generar 12 dígitos base
            $codigoBase = str_pad(rand(100000000000, 999999999999), 12, "0", STR_PAD_LEFT);
            // Calcular EAN-13 completo con dígito verificador
            $codigo = calcularDigitoVerificadorEAN13($codigoBase);
            $check = $conn->query("SELECT id FROM productos WHERE codigo_barras='$codigo'");
        } while ($check->num_rows > 0);
    } else {
        // Si el usuario proporciona un código, validar y completar si es necesario
        $codigo = preg_replace('/[^0-9]/', '', $codigo); // Solo números
        
        if (strlen($codigo) == 12) {
            // Si tiene 12 dígitos, calcular el dígito verificador
            $codigo = calcularDigitoVerificadorEAN13($codigo);
        } elseif (strlen($codigo) == 13) {
            // Si tiene 13 dígitos, verificar que el dígito verificador sea correcto
            $codigoBase = substr($codigo, 0, 12);
            $codigoCalculado = calcularDigitoVerificadorEAN13($codigoBase);
            if ($codigo !== $codigoCalculado) {
                echo "<p style='color:red;'>Error: El código EAN-13 proporcionado no tiene un dígito verificador válido. 
                      El código correcto sería: <strong>$codigoCalculado</strong></p>";
                $codigo = $codigoCalculado;
            }
        } else {
            echo "<p style='color:red;'>Error: El código debe tener 12 o 13 dígitos.</p>";
            return;
        }
        
        // Verificar que el código no exista
        $check = $conn->query("SELECT id FROM productos WHERE codigo_barras='$codigo'");
        if ($check->num_rows > 0) {
            echo "<p style='color:red;'>Error: El código de barras ya existe.</p>";
        } else {
            $codigo = mysqli_real_escape_string($conn, $codigo);
        }
    }

    if (!isset($check) || $check->num_rows == 0) {
        $sql = "INSERT INTO productos (nombre, codigo_barras, cantidad, precio) VALUES ('$nombre','$codigo','$cantidad','$precio')";
        if (mysqli_query($conn, $sql)) {
            echo "<p style='color:blue;'>Producto agregado con éxito. Código EAN-13 válido: <b>$codigo</b></p>";
            header("Location: admin.php");
            exit;
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Panel de Administrador</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/agregar_producto.css">
    <link rel="icon" href="assets/img/ad.png" type="image/png">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 2px;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-barcode { background: #6f42c1; color: white; }
        
        .form-inline {
            display: inline-block;
            margin: 0 5px;
        }
        
        .form-inline input {
            margin-right: 5px;
            padding: 4px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        
        .actions-column {
            min-width: 250px;
        }
        
        .barcode-actions {
            display: flex;
            gap: 5px;
            align-items: center;
            margin-top: 5px;
        }
        
        .barcode-preview {
            max-width: 200px;
            max-height: 100px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 5px;
            display: none;
        }
        
        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            tr {
                border: 1px solid #ccc;
                margin-bottom: 10px;
                padding: 10px;
            }
            
            td {
                border: none;
                position: relative;
                padding-left: 50%;
            }
            
            td:before {
                content: attr(data-label) ": ";
                position: absolute;
                left: 6px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: bold;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Panel de Administrador</h1>

    <!-- Registrar empleado -->
    <div class="section">
        <h2>Registrar Empleado</h2>
        <form method="POST">
            <input type="text" name="nombre" placeholder="Nombre completo" required style="padding: 8px; margin-right: 10px;">
            <button type="submit" name="registrar_empleado" class="btn btn-primary">Registrar</button>
        </form>
    </div>

    <!-- Lista de empleados -->
    <div class="section">
        <h2>Lista de Empleados</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th class="actions-column">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = mysqli_query($conn, "SELECT * FROM empleados ORDER BY id DESC");
                while ($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>
                            <td data-label='ID'>".$row['id']."</td>
                            <td data-label='Código'><strong>".$row['codigo']."</strong></td>
                            <td data-label='Nombre'>".$row['nombre']."</td>
                            <td data-label='Acciones' class='actions-column'>
                                <form method='POST' class='form-inline'>
                                    <input type='hidden' name='id' value='".$row['id']."'>
                                    <input type='text' name='nombre' value='".$row['nombre']."' required style='width:150px;'>
                                    <button type='submit' name='editar_empleado' class='btn btn-success'>Guardar</button>
                                </form>
                                <a href='admin.php?eliminar_empleado=".$row['id']."' 
                                   onclick='return confirm(\"¿Eliminar empleado?\")' 
                                   class='btn btn-danger'>Eliminar</a>
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
            <div style="margin-bottom: 15px;">
                <input type="text" name="nombre" placeholder="Nombre del producto" required 
                       style="padding: 8px; width: 250px; margin-right: 10px;">
                <input type="text" name="codigo" placeholder="Código EAN-13 (12 o 13 dígitos)" 
                       style="padding: 8px; width: 220px; margin-right: 10px;" maxlength="13">
                <input type="number" name="cantidad" placeholder="Cantidad inicial" required 
                       style="padding: 8px; width: 150px; margin-right: 10px;">
                <input type="number" name="precio" step="0.01" min="0" placeholder="Precio" required 
                       style="padding: 8px; width: 120px; margin-right: 10px;">
                <button type="submit" name="agregar_producto" class="btn btn-primary">Guardar</button>
            </div>
            <small style="color: #666;">Puedes ingresar 12 o 13 dígitos. Si ingresas 12, se calculará automáticamente el dígito verificador. Si no ingresas nada, se generará un código EAN-13 completo.</small>
        </form>
    </div>

    <!-- Lista de productos (colapsable) -->
    <div class="section">
        <h2 style="display:inline-block;">Lista de Productos</h2>
        <button id="toggleProductos" class="btn btn-info" style="float:right; margin-top:-8px;">Mostrar/Ocultar</button>
        <div id="productosContainer" style="display:none; margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Código de Barras EAN-13</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th class="actions-column">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = mysqli_query($conn, "SELECT * FROM productos ORDER BY id DESC");
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>
                                <td data-label='ID'>".$row['id']."</td>
                                <td data-label='Nombre'>".$row['nombre']."</td>
                                <td data-label='Código'><strong>".$row['codigo_barras']."</strong></td>
                                <td data-label='Cantidad'>
                                    <span style='background: ".($row['cantidad'] > 10 ? '#28a745' : ($row['cantidad'] > 0 ? '#ffc107' : '#dc3545'))."; 
                                          color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px;'>
                                        ".$row['cantidad']."
                                    </span>
                                </td>
                                <td data-label='Precio'>$".number_format($row['precio'],2)."</td>
                                <td data-label='Acciones' class='actions-column'>
                                    <form method='POST' class='form-inline' style='margin-bottom: 5px;'>
                                        <input type='hidden' name='id' value='".$row['id']."'>
                                        <input type='text' name='nombre' value='".$row['nombre']."' required style='width:120px; margin-right: 5px;'>
                                        <input type='text' name='codigo_barras' value='".$row['codigo_barras']."' required style='width: 110px; margin-right: 5px;' maxlength='13'>
                                        <input type='number' name='cantidad' value='".$row['cantidad']."' required style='width: 60px; margin-right: 5px;'>
                                        <input type='number' name='precio' value='".number_format($row['precio'],2,".","")."' step='0.01' min='0' required style='width: 80px; margin-right: 5px;'>
                                        <button type='submit' name='editar_producto' class='btn btn-success'>Guardar</button>
                                    </form>
                                    <div class='barcode-actions'>
                                        <a href='admin.php?eliminar_producto=".$row['id']."' 
                                           onclick='return confirm(\"¿Eliminar producto?\")' 
                                           class='btn btn-danger'>Eliminar</a>
                                        <button onclick='previewBarcode(\"".$row['codigo_barras']."\", \"".addslashes($row['nombre'])."\", this, \"".$row['precio']."\")' 
                                                class='btn btn-info'>Ver Código</button>
                                        <a href='generar_codigo_barras.php?descargar=1&codigo=".$row['codigo_barras']."&nombre=".urlencode($row['nombre'])."&precio=".$row['precio']."' 
                                           class='btn btn-barcode'>📥 Descargar PNG</a>
                                    </div>
                                    <img class='barcode-preview' id='preview-".$row['id']."' alt='Código de barras'>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div style="clear:both;"></div>
    </div>

    <!-- Estadísticas rápidas -->
    <div class="section">
        <h2>Estadísticas</h2>
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <?php
            $totalEmpleados = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM empleados"))['total'];
            $totalProductos = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM productos"))['total'];
            $productosStockBajo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM productos WHERE cantidad <= 5"))['total'];
            $totalMovimientos = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM movimientos"))['total'];
            ?>
            
            <div style="background: #007bff; color: white; padding: 15px; border-radius: 8px; text-align: center; min-width: 120px;">
                <h3 style="margin: 0; font-size: 24px;"><?php echo $totalEmpleados; ?></h3>
                <p style="margin: 5px 0 0 0;">Empleados</p>
            </div>
            
            <div style="background: #28a745; color: white; padding: 15px; border-radius: 8px; text-align: center; min-width: 120px;">
                <h3 style="margin: 0; font-size: 24px;"><?php echo $totalProductos; ?></h3>
                <p style="margin: 5px 0 0 0;">Productos</p>
            </div>
            
            <div style="background: #dc3545; color: white; padding: 15px; border-radius: 8px; text-align: center; min-width: 120px;">
                <h3 style="margin: 0; font-size: 24px;"><?php echo $productosStockBajo; ?></h3>
                <p style="margin: 5px 0 0 0;">Stock Bajo</p>
            </div>
            
            <div style="background: #6c757d; color: white; padding: 15px; border-radius: 8px; text-align: center; min-width: 120px;">
                <h3 style="margin: 0; font-size: 24px;"><?php echo $totalMovimientos; ?></h3>
                <p style="margin: 5px 0 0 0;">Movimientos</p>
            </div>
        </div>
    </div>
</div>

<script>
// Función para mostrar preview del código de barras
function previewBarcode(codigo, nombre, button, precio) {
    const row = button.closest('tr');
    const preview = row.querySelector('.barcode-preview');
    
    if (preview.style.display === 'block') {
        preview.style.display = 'none';
        button.textContent = 'Ver Código';
    } else {
        preview.src = 'generar_codigo_barras.php?mostrar=1&codigo=' + encodeURIComponent(codigo) + '&nombre=' + encodeURIComponent(nombre) + '&precio=' + encodeURIComponent(precio);
        preview.style.display = 'block';
        button.textContent = 'Ocultar';
    }
}

// Confirmar eliminaciones
document.addEventListener('DOMContentLoaded', function() {
    // Agregar confirmación a todos los enlaces de eliminar
    const deleteLinks = document.querySelectorAll('a[onclick*="confirm"]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('¿Estás seguro de que quieres eliminar este elemento?')) {
                e.preventDefault();
            }
        });
    });
    
    // Validación para códigos de barras de 13 dígitos
    const codigoInputs = document.querySelectorAll('input[name="codigo"], input[name="codigo_barras"]');
    codigoInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            // Solo permitir números
            this.value = this.value.replace(/[^0-9]/g, '');
            // Limitar a 13 caracteres
            if (this.value.length > 13) {
                this.value = this.value.slice(0, 13);
            }
        });
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
});
</script>
</body>
</html>