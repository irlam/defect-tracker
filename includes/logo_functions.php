<?php
// includes/logo_functions.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-25 15:30:35
// Current User's Login: irlam

class LogoManager {
    private $companyLogoPath = 'assets/logos/company/';
    private $contractorLogoPath = 'assets/logos/contractors/';
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private $maxSize = 5242880; // 5MB
    private $db;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->createDirectories();
    }

    // Check permissions
    public function checkPermissions() {
        if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'manager'])) {
            return false;
        }
        return true;
    }

    private function createDirectories() {
        $dirs = [
            $this->companyLogoPath,
            $this->contractorLogoPath
        ];

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    error_log("Failed to create directory: " . $dir);
                    throw new Exception("Failed to create required directories");
                }
            }
        }
    }

    public function uploadLogo($file, $type = 'company', $contractorId = null) {
        try {
            // Basic validations
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                throw new Exception('No file uploaded');
            }

            if (!in_array($file['type'], $this->allowedTypes)) {
                throw new Exception('Invalid file type. Allowed types: JPG, PNG, GIF');
            }

            if ($file['size'] > $this->maxSize) {
                throw new Exception('File too large. Maximum size: 5MB');
            }

            if ($type === 'contractor' && empty($contractorId)) {
                throw new Exception('Contractor ID is required for contractor logos');
            }

            // Process the image
            $imageInfo = getimagesize($file['tmp_name']);
            if (!$imageInfo) {
                throw new Exception('Invalid image file');
            }

            // Generate unique filename with timestamp
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = date('Ymd_His') . '_' . uniqid() . '.' . $extension;

            // Determine path
            $path = ($type === 'contractor') 
                ? $this->contractorLogoPath . $contractorId . '/'
                : $this->companyLogoPath;

            // Create contractor directory if needed
            if ($type === 'contractor' && !file_exists($path)) {
                if (!mkdir($path, 0755, true)) {
                    throw new Exception('Failed to create contractor logo directory');
                }
            }

            $fullPath = $path . $filename;

            // Delete existing logo if any
            if ($type === 'contractor') {
                $this->deleteExistingContractorLogo($contractorId);
            } else {
                $this->deleteExistingCompanyLogo();
            }

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                throw new Exception('Failed to move uploaded file');
            }

            // Update database
            if ($type === 'contractor') {
                $this->updateContractorLogo($contractorId, $filename);
            } else {
                $this->updateCompanyLogo($filename);
            }

            return $filename;

        } catch (Exception $e) {
            error_log("Logo upload error: " . $e->getMessage());
            throw $e;
        }
    }

    private function deleteExistingCompanyLogo() {
        $currentLogo = $this->getCompanyLogo();
        if ($currentLogo && file_exists($currentLogo)) {
            unlink($currentLogo);
        }
    }

    private function deleteExistingContractorLogo($contractorId) {
        $currentLogo = $this->getContractorLogo($contractorId);
        if ($currentLogo && file_exists($currentLogo)) {
            unlink($currentLogo);
        }
    }

    private function updateCompanyLogo($filename) {
        $sql = "INSERT INTO company_settings (setting_key, setting_value, updated_at) 
                VALUES ('company_logo', ?, NOW()) 
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$filename, $filename]);
    }

    private function updateContractorLogo($contractorId, $filename) {
        $sql = "UPDATE contractors SET logo = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$filename, $contractorId]);
    }

    public function getCompanyLogo() {
        $sql = "SELECT setting_value FROM company_settings WHERE setting_key = 'company_logo'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $logo = $this->companyLogoPath . $result['setting_value'];
            return file_exists($logo) ? $logo : null;
        }
        return null;
    }

    public function getContractorLogo($contractorId) {
        $sql = "SELECT logo FROM contractors WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$contractorId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['logo']) {
            $logo = $this->contractorLogoPath . $contractorId . '/' . $result['logo'];
            return file_exists($logo) ? $logo : null;
        }
        return null;
    }

    public function deleteLogo($type = 'company', $contractorId = null) {
        try {
            if ($type === 'contractor' && $contractorId) {
                $this->deleteExistingContractorLogo($contractorId);
                $this->updateContractorLogo($contractorId, null);
                
                // Remove contractor logo directory if empty
                $dir = $this->contractorLogoPath . $contractorId;
                if (is_dir($dir) && count(glob("$dir/*")) === 0) {
                    rmdir($dir);
                }
            } else {
                $this->deleteExistingCompanyLogo();
                $this->updateCompanyLogo(null);
            }
            return true;
        } catch (Exception $e) {
            error_log("Logo deletion error: " . $e->getMessage());
            return false;
        }
    }

    public function cleanupOrphanedLogos() {
        // Clean up company logos
        $sql = "SELECT setting_value FROM company_settings WHERE setting_key = 'company_logo'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $dbLogo = $result['setting_value'] ?? null;
        
        foreach (glob($this->companyLogoPath . "*") as $file) {
            if (basename($file) !== $dbLogo) {
                unlink($file);
            }
        }

        // Clean up contractor logos
        $sql = "SELECT id, logo FROM contractors WHERE logo IS NOT NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $contractorLogos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $contractorLogos[$row['id']] = $row['logo'];
        }

        foreach (glob($this->contractorLogoPath . "*/*") as $file) {
            $contractorId = basename(dirname($file));
            $filename = basename($file);
            if (!isset($contractorLogos[$contractorId]) || $contractorLogos[$contractorId] !== $filename) {
                unlink($file);
            }
        }
    }
}