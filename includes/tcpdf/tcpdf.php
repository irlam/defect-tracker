<?php
/**
 * TCPDF Configuration File
 *
 * You can customize various settings including where TCPDF should look for fonts.
 */

// Define the main TCPDF path (adjust if needed)
define('K_PATH_MAIN', realpath(dirname(__FILE__)) . '/');

// Update the fonts path to be within your allowed open_basedir paths.
// In this example, we point to the fonts folder under httpdocs.
define('K_PATH_FONTS', '/var/www/vhosts/hosting215226.ae97b.netcup.net/mcgoff.defecttracker.uk/httpdocs/includes/tcpdf/fonts/');

// Other configuration settings can remain as default
define('K_PATH_URL', '');
define('K_PATH_CACHE', '/tmp/');
define('K_PATH_CUSTOM', K_PATH_MAIN . 'addons/');

define('PDF_PAGE_FORMAT', 'A4');
define('PDF_PAGE_ORIENTATION', 'P');
define('PDF_UNIT', 'mm');
define('PDF_CREATOR', 'TCPDF');
define('PDF_AUTHOR', 'TCPDF');
define('PDF_HEADER_TITLE', 'TCPDF Example');
define('PDF_HEADER_STRING', "by Niclaus S.   - www.tcpdf.org");
define('PDF_HEADER_LOGO', '');
define('PDF_HEADER_LOGO_WIDTH', 0);
define('PDF_MARGIN_HEADER', 5);
define('PDF_MARGIN_TOP', 27);
define('PDF_MARGIN_BOTTOM', 25);
define('PDF_MARGIN_LEFT', 15);
define('PDF_MARGIN_RIGHT', 15);
define('PDF_FONT_NAME_MAIN', 'helvetica');
define('PDF_FONT_SIZE_MAIN', 10);
define('PDF_FONT_NAME_DATA', 'helvetica');
define('PDF_FONT_SIZE_DATA', 8);
define('PDF_FONT_MONOSPACED', 'courier');
define('PDF_IMAGE_SCALE_RATIO', 1.25);
define('HEAD_MAGNIFICATION', 1.1);
define('K_CELL_HEIGHT_RATIO', 1.25);
define('K_TITLE_MAGNIFICATION', 1.3);
define('K_SMALL_RATIO', 2/3);
define('K_TCPDF_CALLS_IN_HTML', false);


// Simple TCPDF Wrapper
// Put this in includes/tcpdf/tcpdf.php if problems continue

// Log the loading attempt
error_log('TCPDF wrapper loaded from: ' . __FILE__);

// Define the location of the actual TCPDF file
$tcpdf_real_path = __DIR__ . '/tcpdf/tcpdf.php';
if (!file_exists($tcpdf_real_path)) {
    // Try alternative location
    $tcpdf_real_path = dirname(__DIR__) . '/tcpdf/tcpdf.php';
}

// Check if we found the real TCPDF file
if (!file_exists($tcpdf_real_path)) {
    error_log("ERROR: Real TCPDF file not found at: " . $tcpdf_real_path);
    die("TCPDF library not found. Please check server configuration.");
} else {
    // Load the actual TCPDF library
    require_once($tcpdf_real_path);
}