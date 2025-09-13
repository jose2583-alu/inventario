<?php
// Función para generar código de barras EAN-13
function generarCodigoBarrasEAN13($codigo, $nombre_producto = '') {
    // Validar que el código tenga exactamente 13 dígitos
    if (strlen($codigo) !== 13 || !ctype_digit($codigo)) {
        return false;
    }
    
    // Patrones para EAN-13
    $left_odd = array(
        '0001101', '0011001', '0010011', '0111101', '0100011',
        '0110001', '0101111', '0111011', '0110111', '0001011'
    );
    
    $left_even = array(
        '0100111', '0110011', '0011011', '0100001', '0011101',
        '0111001', '0000101', '0010001', '0001001', '0010111'
    );
    
    $right = array(
        '1110010', '1100110', '1101100', '1000010', '1011100',
        '1001110', '1010000', '1000100', '1001000', '1110100'
    );
    
    $first_digit_patterns = array(
        'LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG',
        'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'
    );
    
    // Extraer dígitos
    $first_digit = intval($codigo[0]);
    $left_group = substr($codigo, 1, 6);
    $right_group = substr($codigo, 7, 6);
    
    // Obtener patrón para el primer dígito
    $pattern = $first_digit_patterns[$first_digit];
    
    // Construir código binario
    $binary = '101'; // Start guard
    
    // Grupo izquierdo
    for ($i = 0; $i < 6; $i++) {
        $digit = intval($left_group[$i]);
        if ($pattern[$i] === 'L') {
            $binary .= $left_odd[$digit];
        } else {
            $binary .= $left_even[$digit];
        }
    }
    
    $binary .= '01010'; // Center guard
    
    // Grupo derecho
    for ($i = 0; $i < 6; $i++) {
        $digit = intval($right_group[$i]);
        $binary .= $right[$digit];
    }
    
    $binary .= '101'; // End guard
    
    // Generar imagen
    $bar_width = 3;
    $bar_height = 120;
    $text_height = 30;
    $margin = 20;
    
    $img_width = (strlen($binary) * $bar_width) + (2 * $margin);
    $img_height = $bar_height + $text_height + (2 * $margin);
    
    $image = @imagecreate($img_width, $img_height);
    if (!$image) {
        return false;
    }
    $white = @imagecolorallocate($image, 255, 255, 255);
    $black = @imagecolorallocate($image, 0, 0, 0);
    if ($white === false || $black === false) {
        imagedestroy($image);
        return false;
    }
    
    // Fondo blanco
    imagefill($image, 0, 0, $white);
    
    // Dibujar barras
    $x = $margin;
    for ($i = 0; $i < strlen($binary); $i++) {
        if ($binary[$i] === '1') {
            imagefilledrectangle($image, $x, $margin, $x + $bar_width - 1, $margin + $bar_height, $black);
        }
        $x += $bar_width;
    }
    
    // Agregar texto del código
    $font_size = 3;
    $text_width = imagefontwidth($font_size) * strlen($codigo);
    $text_x = ($img_width - $text_width) / 2;
    $text_y = $margin + $bar_height + 5;
    
    imagestring($image, $font_size, $text_x, $text_y, $codigo, $black);
    
    // Agregar nombre del producto si se proporciona
    if (!empty($nombre_producto)) {
        $font_size_name = 2;
        $name_width = imagefontwidth($font_size_name) * strlen($nombre_producto);
        $name_x = ($img_width - $name_width) / 2;
        $name_y = $text_y + 20;
        
        imagestring($image, $font_size_name, $name_x, $name_y, $nombre_producto, $black);
    }
    
    return $image;
}

// Procesar solicitud de descarga
if (isset($_GET['descargar']) && isset($_GET['codigo']) && isset($_GET['nombre'])) {
    $codigo = trim($_GET['codigo']);
    $nombre = trim($_GET['nombre']);
    
    $imagen = generarCodigoBarrasEAN13($codigo, $nombre);
    
    if ($imagen) {
        // Limpiar buffer de salida antes de enviar cabeceras
        if (ob_get_length()) ob_end_clean();
        // Configurar headers para descarga
        $filename = 'codigo_barras_' . $codigo . '.png';
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
        // Enviar imagen
        imagepng($imagen);
        imagedestroy($imagen);
        exit;
    } else {
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: text/plain; charset=utf-8');
        echo "Error: Código de barras inválido o error al generar la imagen";
        exit;
    }
}

// Mostrar imagen en el navegador
if (isset($_GET['mostrar']) && isset($_GET['codigo']) && isset($_GET['nombre'])) {
    $codigo = trim($_GET['codigo']);
    $nombre = trim($_GET['nombre']);
    
    $imagen = generarCodigoBarrasEAN13($codigo, $nombre);
    
    if ($imagen) {
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: image/png');
        imagepng($imagen);
        imagedestroy($imagen);
        exit;
    } else {
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: text/plain; charset=utf-8');
        echo "Error: Código de barras inválido o error al generar la imagen";
        exit;
    }
}
