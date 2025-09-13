<?php
/**
 * includes/generate_defect_image.php
 * Generates pin location images for defects
 * Current Date and Time (UTC): 2025-01-30 15:09:56
 * Current User's Login: irlam
 */

declare(strict_types=1);

require_once 'upload_constants.php'; // Include upload constants

class DefectImageGenerator {
    private $defectId;
    private $uploadPath;
    private $db;
    private $errors = [];

    /**
     * Constructor
     * 
     * @param int $defectId The ID of the defect
     * @throws Exception If upload path cannot be created
     */
    public function __construct(int $defectId) {
        $this->defectId = $defectId;
        
        // Set upload path
        $this->uploadPath = UPLOAD_BASE_DIR . '/defect_' . $defectId . '/';
        
        // Ensure upload directory exists
        if (!file_exists($this->uploadPath)) {
            if (!mkdir($this->uploadPath, 0755, true)) {
                throw new Exception("Failed to create upload directory: " . $this->uploadPath);
            }
        }

        // Initialize database connection
        try {
            $database = new Database();
            $this->db = $database->getConnection();
        } catch (Exception $e) {
            error_log("Database connection error in DefectImageGenerator: " . $e->getMessage());
            throw $e;
        }
    }
}

    /**
     * Generate the pin image for the defect
     * 
     * @return string The filename of the generated image
     * @throws Exception If image generation fails
     */
    public function generate(): string {
        try {
            // Get defect details
            $defect = $this->getDefectDetails();
            if (!$defect) {
                throw new Exception("Defect not found: " . $this->defectId);
            }

            // Get floor plan image
            $floorPlanPath = $this->getFloorPlanPath($defect['floor_plan_id']);
            if (!$floorPlanPath || !file_exists($floorPlanPath)) {
                throw new Exception("Floor plan not found for defect: " . $this->defectId);
            }

            // Generate unique filename
            $filename = sprintf('defect_pin_%d_%s.png', 
                $this->defectId, 
                date('YmdHis')
            );
            
            $fullPath = $this->uploadPath . $filename;

            // Create image
            $result = $this->createPinImage(
                $floorPlanPath,
                $fullPath,
                (float)$defect['pin_x'],
                (float)$defect['pin_y']
            );

            if (!$result) {
                throw new Exception("Failed to generate pin image for defect: " . $this->defectId);
            }

            return $filename;

        } catch (Exception $e) {
            error_log("Error in DefectImageGenerator::generate: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get defect details from database
     * 
     * @return array|false Defect details or false if not found
     */
    private function getDefectDetails() {
        try {
            $stmt = $this->db->prepare("
                SELECT d.*, f.file_path as floor_plan_path
                FROM defects d
                JOIN floor_plans f ON d.floor_plan_id = f.id
                WHERE d.id = :id
            ");
            $stmt->execute([':id' => $this->defectId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Database error in getDefectDetails: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get floor plan path
     * 
     * @param int $floorPlanId Floor plan ID
     * @return string|false Floor plan path or false if not found
     */
    private function getFloorPlanPath(int $floorPlanId) {
        try {
            $stmt = $this->db->prepare("
                SELECT file_path 
                FROM floor_plans 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $floorPlanId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['file_path'] : false;
        } catch (Exception $e) {
            error_log("Database error in getFloorPlanPath: " . $e->getMessage());
            throw $e;
        }
    }
    }

    /**
     * Create pin image
     * 
     * @param string $floorPlanPath Path to floor plan PDF
     * @param string $outputPath Path to save output image
     * @param float $pinX X coordinate of pin (0-1)
     * @param float $pinY Y coordinate of pin (0-1)
     * @return bool True if successful, false otherwise
     */
    private function createPinImage(
        string $floorPlanPath, 
        string $outputPath, 
        float $pinX, 
        float $pinY
    ): bool {
        try {
            // Convert PDF to image if necessary
            if (strtolower(pathinfo($floorPlanPath, PATHINFO_EXTENSION)) === 'pdf') {
                $tempImage = $this->convertPdfToImage($floorPlanPath);
                if (!$tempImage) {
                    throw new Exception("Failed to convert PDF to image");
                }
                $floorPlanPath = $tempImage;
            }

            // Load floor plan image
            $source = imagecreatefromstring(file_get_contents($floorPlanPath));
            if (!$source) {
                throw new Exception("Failed to load floor plan image");
            }

            // Get image dimensions
            $width = imagesx($source);
            $height = imagesy($source);

            // Calculate pin position
            $pinPosX = (int)($width * $pinX);
            $pinPosY = (int)($height * $pinY);

            // Create pin marker
            $this->drawPin($source, $pinPosX, $pinPosY);

            // Save image
            $result = imagepng($source, $outputPath);
            imagedestroy($source);

            // Clean up temporary files
            if (isset($tempImage) && file_exists($tempImage)) {
                unlink($tempImage);
            }

            return $result;

        } catch (Exception $e) {
            error_log("Error in createPinImage: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Draw pin on image
     * 
     * @param resource $image Image resource
     * @param int $x X coordinate
     * @param int $y Y coordinate
     */
    private function drawPin($image, int $x, int $y): void {
        // Pin colors
        $red = imagecolorallocate($image, 255, 0, 0);
        $white = imagecolorallocate($image, 255, 255, 255);

        // Draw pin circle
        imagefilledellipse($image, $x, $y, 24, 24, $red);
        imagefilledellipse($image, $x, $y, 8, 8, $white);

        // Add border
        imageellipse($image, $x, $y, 24, 24, $white);
    }

    /**
     * Convert PDF to image
     * 
     * @param string $pdfPath Path to PDF file
     * @return string|false Path to generated image or false on failure
     */
    private function convertPdfToImage(string $pdfPath) {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'defect_');
            $command = sprintf(
                'convert -density 150 %s[0] -quality 90 %s',
                escapeshellarg($pdfPath),
                escapeshellarg($tempFile . '.png')
            );
            
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new Exception("PDF conversion failed: " . implode("\n", $output));
            }
            
            return $tempFile . '.png';

        } catch (Exception $e) {
            error_log("Error converting PDF to image: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get any errors that occurred
     * 
     * @return array Array of error messages
     */
    public function getErrors(): array {
        return $this->errors;
    }
}

?>