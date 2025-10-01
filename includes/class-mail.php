<?php
/**
 * VivalaTable Mail System
 * Replacement for WordPress wp_mail
 */

class VT_Mail {

    public static function send($to, $subject, $message, $headers = '', $attachments = []) {
        // Get SMTP configuration
        $smtp_config = [
            'host' => VT_Config::get('smtp_host', 'localhost'),
            'port' => VT_Config::get('smtp_port', 587),
            'username' => VT_Config::get('smtp_username', ''),
            'password' => VT_Config::get('smtp_password', ''),
            'secure' => VT_Config::get('smtp_secure', 'tls')
        ];

        // Default from email
        $from_email = VT_Config::get('admin_email', 'noreply@vivalatable.com');
        $from_name = VT_Config::get('site_title', 'VivalaTable');

        // Parse headers
        $parsed_headers = self::parseHeaders($headers);

        // Set up mail headers
        $mail_headers = [
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email,
            'X-Mailer: VivalaTable',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8'
        ];

        // Add custom headers
        if (!empty($parsed_headers)) {
            $mail_headers = array_merge($mail_headers, $parsed_headers);
        }

        // Convert to array if single recipient
        if (!is_array($to)) {
            $to = [$to];
        }

        $success = true;

        foreach ($to as $recipient) {
            if (self::isValidEmail($recipient)) {
                try {
                    if (!empty($smtp_config['host']) && !empty($smtp_config['username'])) {
                        // Use SMTP
                        $result = self::sendViaSMTP($recipient, $subject, $message, $mail_headers, $smtp_config, $attachments);
                    } else {
                        // Use PHP mail()
                        $result = self::sendViaPHPMail($recipient, $subject, $message, $mail_headers, $attachments);
                    }

                    if (!$result) {
                        $success = false;
                        error_log("Failed to send email to: $recipient");
                    }
                } catch (Exception $e) {
                    $success = false;
                    error_log("Mail error: " . $e->getMessage());
                }
            } else {
                $success = false;
                error_log("Invalid email address: $recipient");
            }
        }

        return $success;
    }

    private static function sendViaPHPMail($to, $subject, $message, $headers, $attachments) {
        $header_string = implode("\r\n", $headers);

        if (!empty($attachments)) {
            // Handle attachments - simplified implementation
            $boundary = uniqid('boundary_');
            $header_string .= "\r\nContent-Type: multipart/mixed; boundary=\"$boundary\"";

            $body = "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= $message . "\r\n\r\n";

            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $body .= "--$boundary\r\n";
                    $body .= "Content-Type: application/octet-stream\r\n";
                    $body .= "Content-Disposition: attachment; filename=\"" . basename($attachment) . "\"\r\n";
                    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                    $body .= chunk_split(base64_encode(file_get_contents($attachment))) . "\r\n";
                }
            }

            $body .= "--$boundary--";
            $message = $body;
        }

        return mail($to, $subject, $message, $header_string);
    }

    private static function sendViaSMTP($to, $subject, $message, $headers, $config, $attachments) {
        // Simple SMTP implementation - in production use PHPMailer or similar
        $socket = fsockopen($config['host'], $config['port'], $errno, $errstr, 30);

        if (!$socket) {
            throw new Exception("Cannot connect to SMTP server: $errstr ($errno)");
        }

        // SMTP conversation
        $commands = [
            "EHLO " . $_SERVER['HTTP_HOST'],
            "STARTTLS",
            "AUTH LOGIN",
            base64_encode($config['username']),
            base64_encode($config['password']),
            "MAIL FROM: <" . VT_Config::get('admin_email') . ">",
            "RCPT TO: <$to>",
            "DATA"
        ];

        foreach ($commands as $command) {
            fwrite($socket, $command . "\r\n");
            $response = fgets($socket, 515);

            if (strpos($response, '250') !== 0 && strpos($response, '354') !== 0 && strpos($response, '220') !== 0) {
                fclose($socket);
                throw new Exception("SMTP Error: $response");
            }
        }

        // Send message
        $email_data = implode("\r\n", $headers) . "\r\n";
        $email_data .= "Subject: $subject\r\n\r\n";
        $email_data .= $message . "\r\n.\r\n";

        fwrite($socket, $email_data);
        fwrite($socket, "QUIT\r\n");

        fclose($socket);
        return true;
    }

    private static function parseHeaders($headers) {
        if (empty($headers)) {
            return [];
        }

        if (is_string($headers)) {
            $headers = explode("\n", $headers);
        }

        $parsed = [];
        foreach ($headers as $header) {
            $header = trim($header);
            if (!empty($header)) {
                $parsed[] = $header;
            }
        }

        return $parsed;
    }

    private static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    // Template-based email sending
    public static function sendTemplate($to, $template, $variables = []) {
        $template_path = VT_ROOT_DIR . '/templates/emails/' . $template . '.php';

        if (!file_exists($template_path)) {
            error_log("Email template not found: $template");
            return false;
        }

        // Extract variables for template
        extract($variables);

        // Capture template output
        ob_start();
        include $template_path;
        $message = ob_get_clean();

        // Get subject from template (should be set in template file)
        $subject = $variables['subject'] ?? 'Message from ' . VT_Config::get('site_title');

        return self::send($to, $subject, $message);
    }

    // Common email templates
    public static function sendWelcomeEmail($user_email, $user_name) {
        $variables = [
            'user_name' => $user_name,
            'site_url' => self::getSiteUrl(),
            'site_name' => VT_Config::get('site_title'),
            'subject' => 'Welcome to ' . VT_Config::get('site_title')
        ];

        return self::sendTemplate($user_email, 'welcome', $variables);
    }

    public static function sendInvitationEmail($to_email, $from_name, $event_title, $invitation_url, $custom_message = '') {
        $variables = [
            'to_email' => $to_email,
            'from_name' => $from_name,
            'event_title' => $event_title,
            'invitation_url' => $invitation_url,
            'custom_message' => $custom_message,
            'site_name' => VT_Config::get('site_title'),
            'subject' => "$from_name invited you to $event_title"
        ];

        return self::sendTemplate($to_email, 'invitation', $variables);
    }

    public static function sendRSVPConfirmation($to_email, $event_title, $event_date, $status) {
        $variables = [
            'event_title' => $event_title,
            'event_date' => $event_date,
            'status' => $status,
            'site_name' => VT_Config::get('site_title'),
            'subject' => "RSVP Confirmation: $event_title"
        ];

        return self::sendTemplate($to_email, 'rsvp_confirmation', $variables);
    }

    private static function getSiteUrl() {
        return 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
    }
}