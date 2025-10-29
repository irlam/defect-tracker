<?php
// api/update_system_config.php
require_once '../config/database.php';
require_once 'BaseAPI.php';

class SystemConfigAPI extends BaseAPI {
    public function updateConfig() {
        try {
            foreach ($_POST as $key => $value) {
                if ($key !== 'updated_by' && $key !== 'updated_at') {
                    $stmt = $this->db->prepare("
                        UPDATE system_configurations 
                        SET config_value = :value,
                            updated_by = :updated_by,
                            updated_at = :updated_at
                        WHERE config_key = :key
                    ");
                    
                    $stmt->execute([
                        ':key' => $key,
                        ':value' => $value,
                        ':updated_by' => $this->currentUser,
                        ':updated_at' => $this->currentDateTime
                    ]);
                }
            }
            
            $this->sendResponse(true, 'Configuration updated successfully');
        } catch (Exception $e) {
            $this->sendResponse(false, 'Error updating configuration: ' . $e->getMessage(), 500);
        }
    }
}

$api = new SystemConfigAPI(Database::getInstance()->getConnection());
$api->updateConfig();