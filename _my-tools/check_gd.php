<?php
// check_gd.php
// A simple PHP script to check if the GD library is installed and display its version and supported features.

if (extension_loaded('gd')) {
    echo "GD library is installed.\n";
    $gdInfo = gd_info();
    echo "GD Version: " . $gdInfo['GD Version'] . "\n";
    
    // Display all GD info
    foreach ($gdInfo as $key => $value) {
        if (is_bool($value)) {
            echo $key . ": " . ($value ? 'Yes' : 'No') . "\n";
        } else {
            echo $key . ": " . $value . "\n";
        }
    }
} else {
    echo "GD library is NOT installed.\n";
}
?>