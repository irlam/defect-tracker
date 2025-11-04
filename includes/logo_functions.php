<?php

// Load constants if available (for COMPANY_CONTRACTOR_ID)
if (file_exists(__DIR__ . '/../config/constants.php')) {
    require_once __DIR__ . '/../config/constants.php';
}

class LogoManager {
    private $uploadDir; // Absolute filesystem directory to store logos
    private $publicPathBase = '/uploads/logos/'; // Public path prefix for logos
    private $maxFileSize = 5 * 1024 * 1024; // 5MB
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif']; // Allowed file types
    private $permissions = ['admin', 'editor']; // Roles allowed to upload/delete logos
    private $db; // Database connection

    public function __construct() {
        global $db; // Access the global database connection
        $this->db = $db;

        $rootPath = realpath(__DIR__ . '/..');
        if ($rootPath === false) {
            $rootPath = dirname(__DIR__);
        }

        $this->uploadDir = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'logos' . DIRECTORY_SEPARATOR;

        // Ensure the upload directory exists
        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0775, true)) {
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
        $fileName = $this->generateFileName($file['name']);
        $targetPath = $this->uploadDir . $fileName;

        // Move the uploaded file to the target directory
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to move uploaded file.');
        }

        // Save just the filename to database (not the full path)
        // Database format: filename.png (e.g., "67adb16de7b85_mcgoff.png")
        // This matches existing database records and is required for PDF exports
        // which build filesystem paths using: $_SERVER['DOCUMENT_ROOT'] . '/uploads/logos/' . filename
        $this->saveLogoPath($fileName, $type, $contractorId);

        // Return the normalized public path for display
        return $this->normaliseLogoPath($fileName);
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

    private function generateFileName($originalName) {
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $sanitisedBase = preg_replace('/[^a-z0-9_\-]+/i', '_', $baseName);
        if ($sanitisedBase === '' || $sanitisedBase === null) {
            $sanitisedBase = 'logo';
        }

        $uniquePrefix = str_replace('.', '', uniqid($sanitisedBase . '_', true));
        return $extension ? $uniquePrefix . '.' . $extension : $uniquePrefix;
    }

    private function saveLogoPath($filePath, $type, $contractorId = null) {
        try {
            // Determine which table to update based on logo type
            if ($type === 'company') {
                // Use constant if defined, otherwise default to 1
                $companyId = defined('COMPANY_CONTRACTOR_ID') ? COMPANY_CONTRACTOR_ID : 1;
                
                //For company logo, store in contractors table
                $sql = "UPDATE contractors SET logo = ? WHERE id = ?";

                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([$filePath, $companyId]);

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
        // Use constant if defined, otherwise default to 1
        $companyId = defined('COMPANY_CONTRACTOR_ID') ? COMPANY_CONTRACTOR_ID : 1;
        
        // Retrieve company logo path from contractors table
        $sql = "SELECT logo FROM contractors WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$companyId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['logo']) {
            return $this->normaliseLogoPath($result['logo']);
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
            return $this->normaliseLogoPath($result['logo']);
        }

        return null;
    }

    public function deleteLogo($type, $contractorId = null) {
        try {
            if ($type === 'company') {
                // Get the current company logo path
                $currentLogo = $this->getCompanyLogo();
                
                // Use constant if defined, otherwise default to 1
                $companyId = defined('COMPANY_CONTRACTOR_ID') ? COMPANY_CONTRACTOR_ID : 1;

                // Delete the company logo path from contractors table
                 $sql = "UPDATE contractors SET logo = NULL WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([$companyId]);


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
            $filesystemPath = $this->resolveFilesystemPath($currentLogo ?? null);
            if ($filesystemPath && file_exists($filesystemPath)) {
                unlink($filesystemPath);
            }

            return true;
        } catch (Exception $e) {
            error_log('Error deleting logo: ' . $e->getMessage());
            return false;
        }
    }

    private function normaliseLogoPath($path) {
        if (empty($path)) {
            return null;
        }

        $trimmedPath = trim($path);

        if ($trimmedPath === '') {
            return null;
        }

        // If it's already a full URL, return as-is
        if (preg_match('#^https?://#i', $trimmedPath)) {
            return $trimmedPath;
        }

        // Remove any leading slashes
        $trimmedPath = ltrim($trimmedPath, '/');
        $uploadPrefix = trim($this->publicPathBase, '/');

        // If path already includes uploads/logos/, just add leading slash
        if (stripos($trimmedPath, $uploadPrefix) === 0) {
            return '/' . $trimmedPath;
        }

        // Otherwise, it's just a filename, so prepend the uploads/logos/ path
        return '/' . rtrim($uploadPrefix, '/') . '/' . $trimmedPath;
    }

    private function resolveFilesystemPath($path) {
        if (empty($path)) {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return null;
        }

        $trimmedPath = ltrim($path, '/');
        $uploadPrefix = trim($this->publicPathBase, '/');

        if (stripos($trimmedPath, $uploadPrefix) === 0) {
            $trimmedPath = substr($trimmedPath, strlen($uploadPrefix));
        }

        $trimmedPath = ltrim($trimmedPath, '/');

        if ($trimmedPath === '') {
            return null;
        }

        $safeFilename = basename($trimmedPath);
        return $this->uploadDir . $safeFilename;
    }
}