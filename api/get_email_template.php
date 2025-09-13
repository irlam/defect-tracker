<?php
// api/get_email_template.php
require_once '../config/database.php';
require_once 'BaseAPI.php';

class EmailTemplateAPI extends BaseAPI {
    public function getTemplate() {
        try {
            $templateId = $_GET['id'] ?? null;
            if (!$templateId) {
                $this->sendResponse(false, 'Template ID is required', 400);
                return;
            }

            $stmt = $this->db->prepare("
                SELECT * FROM email_templates 
                WHERE id = ?
            ");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                $this->sendResponse(false, 'Template not found', 404);
                return;
            }

            $this->sendResponse(true, 'Template retrieved successfully', 200, $template);
        } catch (Exception $e) {
            $this->sendResponse(false, 'Error retrieving template: ' . $e->getMessage(), 500);
        }
    }
}

$api = new EmailTemplateAPI(Database::getInstance()->getConnection());
$api->getTemplate();