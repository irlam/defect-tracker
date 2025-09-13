<?php
exec("convert -version", $output, $return_var);
if ($return_var == 0) {
    echo "ImageMagick is installed:\n";
    echo implode("\n", $output);
} else {
    echo "ImageMagick is not installed.";
}
?>