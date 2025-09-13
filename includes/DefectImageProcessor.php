<?php
/**
 * DefectImageProcessor.php
 * Handles image processing tasks for defects, including overlaying pin icons.
 * Current Date and Time (UTC): 2025-02-12 11:55:09
 * Current User's Login: irlam
 */

class DefectImageProcessor {
    /**
     * Creates a defect image by overlaying an SVG pin on a source image.
     * Only generates floorplan_with_pin_defect images, optimized for resource usage.
     *
     * @param string $sourcePath The path to the source image (floor plan).
     * @param string $outputDir The directory where the resulting image should be saved.
     * @param float $pinX The X coordinate of the pin (0-1, relative to the image width).
     * @param float $pinY The Y coordinate of the pin (0-1, relative to the image height).
     * @param array $options An array of options for customizing the pin appearance.
     * @return array An array containing the status and path of the generated image.
     */
    public function createDefectImage($sourcePath, $outputDir, $pinX, $pinY, $options = []) {
        try {
            // Default options
            $defaults = [
                'pinSize' => 250,
                'svgPath' => 'uploads/images/location-pin.svg',
                'pinColor' => null,
                'opacity' => 1.0,
                'colorizeStrength' => 1.0
            ];
            $options = array_merge($defaults, $options);

            // Validate opacity and colorize strength
            $options['opacity'] = max(0.0, min(1.0, floatval($options['opacity'])));
            $options['colorizeStrength'] = max(0.0, min(1.0, floatval($options['colorizeStrength'])));

            // Create Imagick instance for source image
            $sourceImage = new Imagick();
            
            // Handle PDFs differently from images
            if (strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'pdf') {
                $sourceImage->setResolution(450, 450);
                $sourceImage->readImage($sourcePath . '[0]');
                $sourceImage->setImageFormat('png');
                $sourceImage->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            } else {
                $sourceImage->readImage($sourcePath);
            }

            // Get image dimensions
            $width = $sourceImage->getImageWidth();
            $height = $sourceImage->getImageHeight();

            // Convert relative coordinates to absolute pixel coordinates
            $pinXPixel = round($pinX * $width);
            $pinYPixel = round($pinY * $height);

            // Load and prepare the SVG pin
            try {
                $pin = new Imagick($options['svgPath']);
                
                // Apply color if specified
                if (!empty($options['pinColor'])) {
                    $rgb = $this->hexToRGB($options['pinColor']);
                    
                    // Apply colorization
                    $pin->modulate(100, 0, 100);
                    $pin->colorize(
                        $rgb['r'], 
                        $rgb['g'], 
                        $rgb['b'],
                        new ImagickPixel(
                            "rgb(" . 
                            (100 - ($options['colorizeStrength'] * 100)) . "%," .
                            (100 - ($options['colorizeStrength'] * 100)) . "%," .
                            (100 - ($options['colorizeStrength'] * 100)) . "%)"
                        )
                    );
                }

                // Apply opacity if less than 1
                if ($options['opacity'] < 1.0) {
                    $pin->evaluateImage(
                        Imagick::EVALUATE_MULTIPLY,
                        $options['opacity'],
                        Imagick::CHANNEL_ALPHA
                    );
                }
                
                // Resize pin while maintaining aspect ratio
                $pin->resizeImage(
                    $options['pinSize'],
                    $options['pinSize'],
                    Imagick::FILTER_LANCZOS,
                    1,
                    true
                );
                
                // Calculate pin position (centered on specified coordinates)
                $pinWidth = $pin->getImageWidth();
                $pinHeight = $pin->getImageHeight();
                $pinPosX = $pinXPixel - ($pinWidth / 2);
                $pinPosY = $pinYPixel - $pinHeight;
                
                // Composite the pin onto the main image
                $sourceImage->compositeImage(
                    $pin,
                    Imagick::COMPOSITE_OVER,
                    $pinPosX,
                    $pinPosY
                );
                
                // Clean up pin image immediately
                $pin->destroy();
                
            } catch (Exception $e) {
                throw new Exception("Error processing SVG pin: " . $e->getMessage());
            }

            // Create output filename
            $outputFilename = 'floorplan_with_pin_defect_' . uniqid() . '.png';
            $outputPath = rtrim($outputDir, '/') . '/' . $outputFilename;

            // Ensure output directory exists
            if (!is_dir($outputDir)) {
                if (!mkdir($outputDir, 0755, true)) {
                    throw new Exception("Failed to create output directory: $outputDir");
                }
            }

            // Save the image directly to final location
            $sourceImage->setImageFormat('png');
            if (!$sourceImage->writeImage($outputPath)) {
                throw new Exception("Failed to save image to: $outputPath");
            }

            // Clean up source image immediately
            $sourceImage->destroy();

            return [
                'status' => 'success',
                'path' => $outputPath,
                'filename' => $outputFilename
            ];

        } catch (Exception $e) {
            error_log("DefectImageProcessor error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Converts a hex color code to an RGB array.
     *
     * @param string $hex The hex color code (e.g., '#FF0000' or 'red').
     * @return array An array containing the red, green, and blue values.
     */
    private function hexToRGB($hex) {
        $hex = ltrim($hex, '#');

        // Standard color mappings
        $namedColors = [
            'red' => 'FF0000',
            'green' => '00FF00',
            'blue' => '0000FF',
            'white' => 'FFFFFF',
            'black' => '000000',
            'yellow' => 'FFFF00',
            'purple' => '800080',
            'orange' => 'FFA500',
            'gray' => '808080'
        ];

        // Convert named colors to hex
        if (isset($namedColors[strtolower($hex)])) {
            $hex = $namedColors[strtolower($hex)];
        }

        // Validate hex length
        if (strlen($hex) != 6) {
            error_log("Invalid hex code length: " . $hex);
            return ['r' => 0, 'g' => 0, 'b' => 0];
        }

        // Convert hex to RGB
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
}