<?php

class LogoManager {
    private $uploadDir = 'uploads/logos/'; // Directory to store logos
    private $maxFileSize = 5 * 1024 * 1024; // 5MB
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif']; // Allowed file types
    private $permissions = ['admin', 'editor']; // Roles allowed to upload/delete logos
    private $db; // Database connection

    public function __construct() {
        global $db; // Access the global database connection
        $this->db = $db;

        // Ensure the upload directory exists
        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0777, true)) {
            throw new Exception('Failed to create upload directory.');
        }
    }

    public function checkPermissions() {
        // Check if user is logged in and has required role
        if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], $this->permissions)) {
            return false;
        }
        return true;
    }

    public function uploadLogo($file, $type, $contractorId = null) {
        // Validate file upload
        $this->validateFile($file);

        // Generate a unique file name
        $fileName = uniqid() . '_' . basename($file['name']);
        $targetPath = $this->uploadDir . $fileName;

        // Move the uploaded file to the target directory
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to move uploaded file.');
        }

        // Save path to database
        $this->saveLogoPath($targetPath, $type, $contractorId);

        return $targetPath;
    }

    private function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }

        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File size exceeds the maximum limit of ' . ($this->maxFileSize / 1024 / 1024) . 'MB.');
        }

        if (!in_array($file['type'], $this->allowedTypes)) {
            throw new Exception('Invalid file type. Allowed types are JPG, PNG, and GIF.');
        }
    }

    private function saveLogoPath($filePath, $type, $contractorId = null) {
        try {
            // Determine which table to update based on logo type
            if ($type === 'company') {
                //For company logo, store in contractors table (as requested)
                $sql = "UPDATE contractors SET logo = ? WHERE id = 1"; // Assuming company info is stored with ID 1

                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([$filePath]);

            } elseif ($type === 'contractor' && $contractorId) {
                // Update contractor logo path in the contractors table
                $sql = "UPDATE contractors SET logo = ? WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([$filePath, $contractorId]);
            } else {
                throw new Exception('Invalid logo type or contractor ID.');
            }

            if (!$result) {
                throw new Exception('Failed to save logo path to the database.');
            }
        } catch (Exception $e) {
            error_log('Error saving logo path: ' . $e->getMessage());
            throw $e; // Re-throw the exception to be caught in the main script
        }
    }

    public function getCompanyLogo() {
        // Retrieve company logo path from contractors table (as requested)
        $sql = "SELECT logo FROM contractors WHERE id = 1"; // Assuming company info is stored with ID 1
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['logo']) {
            return $result['logo'];
        }

        return null;
    }

    public function getContractorLogo($contractorId) {
        // Retrieve contractor logo path from contractors table
        $sql = "SELECT logo FROM contractors WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$contractorId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['logo']) {
            return $result['logo'];
        }

        return null;
    }

    public function deleteLogo($type, $contractorId = null) {
        try {
            if ($type === 'company') {
                // Get the current company logo path
                $currentLogo = $this->getCompanyLogo();

                // Delete the company logo path from contractors table (as requested)
                 $sql = "UPDATE contractors SET logo = NULL WHERE id = 1"; // Assuming company info is stored with ID 1
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute();


                if (!$result) {
                    throw new Exception('Failed to delete company logo path from the database.');
                }
            } elseif ($type === 'contractor' && $contractorId) {
                // Get the current contractor logo path
                $currentLogo = $this->getContractorLogo($contractorId);

                // Delete the contractor logo path from the contractors table
                $sql = "UPDATE contractors SET logo = NULL WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$contractorId]);
            } else {
                throw new Exception('Invalid logo type or contractor ID.');
            }

            // Delete the actual file if it exists
            if (isset($currentLogo) && file_exists($currentLogo)) {
                unlink($currentLogo);
            }

            return true;
        } catch (Exception $e) {
            error_log('Error deleting logo: ' . $e->getMessage());
            return false;
        }
    }
}