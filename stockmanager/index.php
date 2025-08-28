<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $codigo = $_POST["codigo"];

    if ($codigo === "admin123") {
        header("Location: admin.php");
        exit;
    } else {
        session_start();
        $_SESSION["empleado_codigo"] = $codigo;
        header("Location: empleado.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Inventario - Login</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="icon" href="assets/img/CAG.png" type="image/png">
</head>
<body>
    <h2>Acceso al sistema</h2>
    <form method="POST">
        <label>Código:</label>
        <input type="text" name="codigo" required><br><br>
        <button type="submit">Entrar</button>
    </form>
</body>
</html>