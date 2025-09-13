<?php
/**
 * includes/all_defects_PDF_converter.php
 * PDF to Image Converter (All Defects Version)
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-13 16:15:00
 * Current User's Login: irlam
 */

class AllDefectsPdfConverter {
    private $imagick;
    private $defaultDpi = 300;
    private $maxDpi = 600;

    public function __construct() {
        if (!extension_loaded('imagick')) {
            throw new Exception('Imagick extension is not loaded');
        }
        $this->imagick = new Imagick();

        $formats = Imagick::queryFormats('PDF');
        if (empty($formats)) {
            error_log("Imagick: PDF delegate is NOT configured!");
            throw new Exception("Imagick is not configured to handle PDF files.  Check your Ghostscript installation.");
        } else {
            error_log("Imagick: PDF delegate is configured.");
        }
    }

    /**
     * Convert PDF to image
     * @param string $pdfPath Path to PDF file
     * @param string $outputDir Output directory
     * @param int|null $pageNumber Page number to convert (0-based)
     * @param float $scale Scale factor for output image
     * @return array Status and output information
     */
    public function convertPdfToImage($pdfPath, $outputDir, $pageNumber = 0, $scale = 1.0) {
        try {
            error_log("convertPdfToImage: Starting conversion");

            // Validate input file
            if (!file_exists($pdfPath)) {
                error_log("convertPdfToImage: PDF file not found: {$pdfPath}");
                throw new Exception("PDF file not found: {$pdfPath}");
            }
            error_log("convertPdfToImage: PDF file exists: {$pdfPath}");

            // Validate output directory
            if (!file_exists($outputDir)) {
                error_log("convertPdfToImage: Output directory not found: {$outputDir}");
                if (!mkdir($outputDir, 0755, true)) {
                    error_log("convertPdfToImage: Failed to create output directory: {$outputDir}");
                    throw new Exception("Failed to create output directory: {$outputDir}");
                }
                 error_log("convertPdfToImage: Output directory created: {$outputDir}");
            }

            if (!is_writable($outputDir)) {
                 error_log("convertPdfToImage: Output directory not writable: {$outputDir}");
                throw new Exception("Output directory not writable: {$outputDir}");
            }
             error_log("convertPdfToImage: Output directory is writable: {$outputDir}");

            // Calculate optimal DPI based on PDF size
            $pdfSize = filesize($pdfPath);
             error_log("convertPdfToImage: PDF file size: {$pdfSize}");
            // $dpi = $this->calculateOptimalDpi($pdfSize); // Comment out this line
            $dpi = 250; // Force a lower DPI
             error_log("convertPdfToImage: Forced DPI: {$dpi}");

            // Set density before reading image
            $this->imagick->setResolution($dpi, $dpi);
             error_log("convertPdfToImage: Resolution set to: {$dpi}");

            // Read the PDF
            try {
                $this->imagick->readImage($pdfPath . "[{$pageNumber}]");
                error_log("convertPdfToImage: PDF read successfully");
            } catch (ImagickException $e) {
                error_log("convertPdfToImage: ImagickException during readImage: " . $e->getMessage());
                throw new Exception("ImagickException during readImage: " . $e->getMessage());
            } catch (Exception $e) {
                 error_log("convertPdfToImage: General exception during readImage: " . $e->getMessage());
                throw new Exception("General exception during readImage: " . $e->getMessage());
            }

            // Convert to PNG
            $this->imagick->setImageFormat('png');
             error_log("convertPdfToImage: Image format set to PNG");
            
            // Set compression
            $this->imagick->setImageCompressionQuality(95);
            $this->imagick->setOption('png:compression-level', 9);
             error_log("convertPdfToImage: Compression set");
            
            // Get dimensions
            $width = $this->imagick->getImageWidth();
            $height = $this->imagick->getImageHeight();
             error_log("convertPdfToImage: Dimensions: width={$width}, height={$height}");

            // Scale if needed
            if ($scale !== 1.0) {
                $newWidth = (int)($width * $scale);
                $newHeight = (int)($height * $scale);
                $this->imagick->scaleImage($newWidth, $newHeight);
                $width = $newWidth;
                $height = $newHeight;
                 error_log("convertPdfToImage: Image scaled to: width={$newWidth}, height={$newHeight}");
            }

            // Generate output filename
            $outputFilename = pathinfo($pdfPath, PATHINFO_FILENAME) . '.png';
            $outputPath = $outputDir . '/' . $outputFilename;
             error_log("convertPdfToImage: Output path: {$outputPath}");

            // Write image
            $this->imagick->writeImage($outputPath);
             error_log("convertPdfToImage: Image written to: {$outputPath}");

            // Verify file was created
            if (!file_exists($outputPath)) {
                 error_log("convertPdfToImage: Failed to create image file: {$outputPath}");
                throw new Exception("Failed to create image file");
            }

            // Get output file size
            $outputSize = filesize($outputPath);
             error_log("convertPdfToImage: Output file size: {$outputSize}");

            // Clean up
            $this->imagick->clear();
            $this->imagick->destroy();
             error_log("convertPdfToImage: Resources cleared");

            return [
                'status' => 'success',
                'message' => 'PDF converted successfully',
                'path' => $outputPath,
                'width' => $width,
                'height' => $height,
                'dpi' => $dpi,
                'file_size' => $outputSize
            ];

        } catch (Exception $e) {
            error_log("convertPdfToImage: Exception caught: " . $e->getMessage());
            if ($this->imagick) {
                $this->imagick->clear();
                $this->imagick->destroy();
            }
            throw new Exception("PDF conversion failed: " . $e->getMessage());
        }
    }

    /**
     * Calculate optimal DPI based on PDF file size
     * @param int $fileSize PDF file size in bytes
     * @return int Optimal DPI value
     */
    private function calculateOptimalDpi($fileSize) {
        $sizeMB = $fileSize / 1024 / 1024;
        
        if ($sizeMB <= 1) {
            return $this->maxDpi;
        } elseif ($sizeMB <= 5) {
            return (int)($this->maxDpi * 0.8);
        } elseif ($sizeMB <= 10) {
            return (int)($this->maxDpi * 0.6);
        } else {
            return $this->defaultDpi;
        }
    }

    /**
     * Clean up resources
     */
    public function __destruct() {
        if ($this->imagick) {
            $this->imagick->clear();
            $this->imagick->destroy();
        }
    }
}
?>