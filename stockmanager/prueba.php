<?php
$image = imagecreate(100, 50);
$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 0, 0, 0);
imagestring($image, 5, 10, 20, "Test", $black);
header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
?>
