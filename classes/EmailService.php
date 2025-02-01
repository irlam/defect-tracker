<?php
// classes/EmailService.php
class EmailService {
    private $mailer;
    private $fromEmail;
    private $fromName;

    public function __construct() {
        // Initialize PHPMailer
        $this->mailer = new PHPMailer(true);
        $this->fromEmail = 'noreply@constructiondefecttracker.com';
        $this->fromName = 'Construction Defect Tracker';
        
        // Configure SMTP settings
        $this->mailer->isSMTP();
        $this->mailer->Host = $_ENV['SMTP_HOST'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['SMTP_USERNAME'];
        $this->mailer->Password = $_ENV['SMTP_PASSWORD'];
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = 587;
    }

    public function sendPasswordResetEmail($user, $resetToken) {
        try {
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->addAddress($user['email'], $user['username']);
            $this->mailer->isHTML(true);
            
            $this->mailer->Subject = 'Password Reset Request';
            $this->mailer->Body = $this->getPasswordResetTemplate($user, $resetToken);
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }

    private function getPasswordResetTemplate($user, $resetToken) {
        $resetLink = "https://yourwebsite.com/reset_password.php?token=" . $resetToken;
        
        return "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2>Password Reset Request</h2>
                    <p>Hello {$user['username']},</p>
                    <p>We received a request to reset your password. If you didn't make this request, please ignore this email.</p>
                    <p>To reset your password, click the button below:</p>
                    <p style='text-align: center;'>
                        <a href='{$resetLink}' 
                           style='background-color: #007bff; color: white; padding: 10px 20px; 
                                  text-decoration: none; border-radius: 5px; display: inline-block;'>
                            Reset Password
                        </a>
                    </p>
                    <p>This link will expire in 1 hour.</p>
                    <p>Best regards,<br>Construction Defect Tracker Team</p>
                </div>
            </body>
            </html>
        ";
    }
}
