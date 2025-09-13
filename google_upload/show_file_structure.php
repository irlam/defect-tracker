<?php
/**
 * show_file_structure.php
 * Script to display the file structure of a directory.
 * Current Date and Time (UTC): 2025-01-29 20:24:30
 * Current User's Login: irlam
 */

/**
 * Function to recursively scan a directory and return an array of its structure.
 *
 * @param string $dir The directory to scan.
 * @param int $depth The current depth for indentation (used for recursive calls).
 * @return array The structure of the directory.
 */
function scanDirectory($dir, $depth = 0) {
    $result = [];
    $items = scandir($dir);

    foreach ($items as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $indentation = str_repeat('&nbsp;', $depth * 4);

        if (is_dir($path)) {
            $result[] = "{$indentation}<strong>Directory:</strong> $item";
            $result = array_merge($result, scanDirectory($path, $depth + 1));
        } else {
            $result[] = "{$indentation}File: $item";
        }
    }

    return $result;
}

// Set the directory you want to scan
$directoryToScan = __DIR__; // Current directory
$fileStructure = scanDirectory($directoryToScan);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Structure</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .file-structure {
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <h1>File Structure of <?php echo htmlspecialchars($directoryToScan); ?></h1>
    <div class="file-structure">
        <?php foreach ($fileStructure as $line): ?>
            <div><?php echo $line; ?></div>
        <?php endforeach; ?>
    </div>
</body>
</html>