<?php
include("conexion.php");

$movimientoId = intval($_GET['id']);

if ($movimientoId <= 0) {
    echo "<p style='color:red;'>ID de movimiento inválido</p>";
    exit;
}

// Obtener detalles del movimiento
$sql = "SELECT md.*, 
               p.nombre as producto_nombre,
               p.codigo_barras
        FROM movimientos_detalle md
        JOIN productos p ON md.producto_id = p.id
        WHERE md.movimiento_id = ?
        ORDER BY md.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $movimientoId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<p style='color:orange;'>No se encontraron detalles para este movimiento</p>";
    exit;
}

echo "<table style='width:100%; border-collapse:collapse; margin-top:10px;'>
        <thead>
            <tr style='background:#e9ecef;'>
                <th style='border:1px solid #ddd; padding:8px;'>Producto</th>
                <th style='border:1px solid #ddd; padding:8px;'>Código</th>
                <th style='border:1px solid #ddd; padding:8px;'>Cantidad</th>
                <th style='border:1px solid #ddd; padding:8px;'>Precio Unit.</th>
                <th style='border:1px solid #ddd; padding:8px;'>Subtotal</th>
            </tr>
        </thead>
        <tbody>";

$totalGeneral = 0;
$totalUnidades = 0;

while ($row = $result->fetch_assoc()) {
    echo "<tr>
            <td style='border:1px solid #ddd; padding:6px;'>{$row['producto_nombre']}</td>
            <td style='border:1px solid #ddd; padding:6px; font-family:monospace;'>{$row['codigo_barras']}</td>
            <td style='border:1px solid #ddd; padding:6px; text-align:center;'>{$row['cantidad']}</td>
            <td style='border:1px solid #ddd; padding:6px; text-align:right;'>$".number_format($row['precio_unitario'], 2)."</td>
            <td style='border:1px solid #ddd; padding:6px; text-align:right; font-weight:bold;'>$".number_format($row['subtotal'], 2)."</td>
          </tr>";
    
    $totalGeneral += $row['subtotal'];
    $totalUnidades += $row['cantidad'];
}

echo "</tbody>
      <tfoot>
        <tr style='background:#d4edda; font-weight:bold;'>
            <td colspan='2' style='border:1px solid #ddd; padding:8px;'>TOTALES</td>
            <td style='border:1px solid #ddd; padding:8px; text-align:center;'>{$totalUnidades} unidades</td>
            <td style='border:1px solid #ddd; padding:8px;'></td>
            <td style='border:1px solid #ddd; padding:8px; text-align:right; color:green;'>$".number_format($totalGeneral, 2)."</td>
        </tr>
      </tfoot>
    </table>";

$stmt->close();
?>