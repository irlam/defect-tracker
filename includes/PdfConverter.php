<?php
// includes/PdfConverter.php

class PdfConverter {
    private $imagick;
    private $outputPath;
    
    public function __construct() {
        if (!extension_loaded('imagick')) {
            throw new Exception('Imagick extension is required');
        }
    }

    public function convertPdfToImage($pdfPath, $outputDir, $pageNumber = 0) {
        try {
            // Create output directory if it doesn't exist
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Generate unique filename for the image
            $filename = pathinfo($pdfPath, PATHINFO_FILENAME);
            $outputPath = $outputDir . '/' . $filename . '_' . uniqid() . '.png';

            // Convert PDF to PNG
            $imagick = new Imagick();
            $imagick->setResolution(150, 150); // Set resolution for better quality
            $imagick->readImage($pdfPath . '[' . $pageNumber . ']'); // [0] reads first page
            $imagick->setImageFormat('png');
            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();

            return [
                'status' => 'success',
                'image_path' => $outputPath
            ];
        } catch (Exception $e) {
            error_log("PDF Conversion Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to convert PDF to image'
            ];
        }
    }
}