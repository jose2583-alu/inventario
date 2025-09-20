<?php
// generar_reporte_mes.php
date_default_timezone_set('America/Mexico_City');
include("conexion.php");
header('Content-Type: text/html; charset=utf-8');

if (!isset($_GET['mes'])) {
    http_response_code(400);
    echo "<p>Parámetro 'mes' faltante.</p>"; exit;
}
$mes = $_GET['mes'];
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    http_response_code(400);
    echo "<p>Formato de mes inválido. Use YYYY-MM.</p>"; exit;
}

$start = $mes . '-01';
$dt = DateTime::createFromFormat('Y-m-d', $start);
if (!$dt) { http_response_code(400); echo "<p>Fecha inválida.</p>"; exit; }
$end = $dt->format('Y-m-t');

$tableCheck = $conn->query("SHOW TABLES LIKE 'movimientos_cabecera'");
if (!$tableCheck || $tableCheck->num_rows == 0) {
    echo "<p style='color:darkorange; font-weight:bold;'>La base de datos no tiene la tabla 'movimientos_cabecera'.</p>";
    exit;
}

$startEsc = $conn->real_escape_string($start);
$endEsc = $conn->real_escape_string($end);

$sql = "SELECT mc.id, e.nombre AS empleado, mc.fecha, mc.total_productos, mc.total_valor
        FROM movimientos_cabecera mc
        LEFT JOIN empleados e ON mc.empleado_id = e.id
        WHERE DATE(mc.fecha) BETWEEN '$startEsc' AND '$endEsc'
        ORDER BY mc.fecha ASC";
$result = $conn->query($sql);

$total_row = $conn->query("SELECT COALESCE(SUM(total_valor),0) as total FROM movimientos_cabecera WHERE DATE(fecha) BETWEEN '$startEsc' AND '$endEsc'")->fetch_assoc();
$total_mes = floatval($total_row['total']);

$displayStart = DateTime::createFromFormat('Y-m-d', $start)->format('d/m/Y');
$displayEnd = DateTime::createFromFormat('Y-m-d', $end)->format('d/m/Y');
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Reporte <?php echo htmlspecialchars($mes); ?></title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; color: #222; }
    .header { text-align:center; margin-bottom:10px; }
    .meta { text-align:center; font-size:12px; color:#444; margin-bottom:10px; }
    table { width:100%; border-collapse: collapse; font-size:12px; }
    table th, table td { border:1px solid #ccc; padding:6px; text-align:left; }
    table th { background:#f2f2f2; }
    .right { text-align:right; }
    .totales { margin-top:10px; font-size:14px; font-weight:bold; text-align:right; }
</style>
</head>
<body>
    <div class="header">
        <h2>Reporte de Movimientos</h2>
        <div class="meta">Periodo: <?php echo $displayStart; ?> al <?php echo $displayEnd; ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:8%;">ID</th>
                <th style="width:28%;">Empleado</th>
                <th style="width:22%;">Fecha y Hora</th>
                <th style="width:18%;">Total Productos</th>
                <th style="width:24%;">Total Cobrado (MXN)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $fecha = date('d/m/Y H:i', strtotime($row['fecha']));
                    $emple = htmlspecialchars($row['empleado'] ?: '-', ENT_QUOTES, 'UTF-8');
                    $id = intval($row['id']);
                    $productos = intval($row['total_productos']);
                    $valor = number_format(floatval($row['total_valor']), 2);
                    echo "<tr>
                            <td>#{$id}</td>
                            <td>{$emple}</td>
                            <td>{$fecha}</td>
                            <td class='right'>{$productos}</td>
                            <td class='right'>$ {$valor}</td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='5' style='text-align:center; padding:12px;'>No se encontraron movimientos en este periodo.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <div class="totales">
        Total vendido en el mes: $ <?php echo number_format($total_mes, 2); ?>
    </div>
</body>

</html>
