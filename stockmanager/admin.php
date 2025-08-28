<?php
include("conexion.php");

// === Registrar nuevo empleado ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registrar_empleado'])) {
    $nombre = $_POST['nombre'];

    // Generar código de 4 dígitos aleatorio fijo
    $codigo = str_pad(rand(0, 9999), 4, "0", STR_PAD_LEFT);

    $sql = "INSERT INTO empleados (codigo, nombre) VALUES ('$codigo', '$nombre')";
    if (mysqli_query($conn, $sql)) {
        echo "<p style='color:green;'>Empleado registrado con éxito. Código: <b>$codigo</b></p>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// === Eliminar empleado ===
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $sql = "DELETE FROM empleados WHERE id=$id";
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
    $nombre = $_POST['nombre'];

    $sql = "UPDATE empleados SET nombre='$nombre' WHERE id=$id";
    if (mysqli_query($conn, $sql)) {
        header("Location: admin.php");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// === Agregar producto ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['agregar_producto'])) {
    $nombre = $_POST["nombre"];
    $codigo = $_POST["codigo"];
    $cantidad = $_POST["cantidad"];

    $sql = "INSERT INTO productos (nombre, codigo_barras, cantidad) VALUES ('$nombre','$codigo','$cantidad')";
    if (mysqli_query($conn, $sql)) {
        echo "<p style='color:blue;'>Producto agregado con éxito.</p>";
        header("Location: admin.php");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
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
</head>
<body>
<div class="container">
    <h1>Panel de Administrador</h1>

    <!-- Registrar empleado -->
    <h2>Registrar Empleado</h2>
    <form method="POST">
        <input type="text" name="nombre" placeholder="Nombre completo" required>
        <button type="submit" name="registrar_empleado">Registrar</button>
    </form>

    <!-- Lista de empleados -->
    <h2>Lista de Empleados</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Código</th>
                <th>Nombre</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $result = mysqli_query($conn, "SELECT * FROM empleados ORDER BY id DESC");
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>
                        <td data-label='ID'>".$row['id']."</td>
                        <td data-label='Código'>".$row['codigo']."</td>
                        <td data-label='Nombre'>".$row['nombre']."</td>
                        <td data-label='Acciones'>
                            <form method='POST' style='display:inline-block; margin-bottom:5px;'>
                                <input type='hidden' name='id' value='".$row['id']."'>
                                <input type='text' name='nombre' value='".$row['nombre']."' required>
                                <button type='submit' name='editar_empleado'>Guardar</button>
                            </form>
                        </td>
                      </tr>";
            }
            ?>
        </tbody>
    </table>

    <!-- Agregar producto -->
    <h2>Agregar Producto</h2>
    <form method="POST">
        <input type="text" name="nombre" placeholder="Nombre del producto" required><br><br>
        <input type="text" name="codigo" placeholder="Código de barras"><br><br>
        <input type="number" name="cantidad" placeholder="Cantidad inicial" required><br><br>
        <button type="submit" name="agregar_producto">Guardar</button>
    </form>
</div>
</body>
</html>