<?php
/**
 * VivalaTable Mail System
 * Backward compatibility wrapper - delegates to modern MailService
 */

class VT_Mail {

    public static function send($to, $subject, $message, $headers = '', $attachments = []) {
        // Delegate to modern mail service
        try {
            $mailService = vt_service('mail.service');

            // Warn if headers or attachments are used (not supported in wrapper)
            if (!empty($headers) || !empty($attachments)) {
                self::logWarning('VT_Mail::send() called with headers or attachments - these are ignored in modern MailService wrapper');
            }

            return $mailService->send($to, $subject, $message);
        } catch (Exception $e) {
            self::logError('Mail send failed: ' . $e->getMessage());
            return false;
        }
    }


    // Template-based email sending
    public static function sendTemplate($to, $template, $variables = []) {
        // Delegate to modern mail service
        try {
            $mailService = vt_service('mail.service');
            return $mailService->sendTemplate($to, $template, $variables);
        } catch (Exception $e) {
            self::logError('Template mail send failed: ' . $e->getMessage());
            return false;
        }
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

    private static function logError(string $message): void {
        $logFile = dirname(__DIR__, 3) . '/debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }

    private static function logWarning(string $message): void {
        $logFile = dirname(__DIR__, 3) . '/debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] WARNING: {$message}\n", FILE_APPEND);
    }
}