<?php
// includes/mail.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/**
 * Sends an email notification.
 * Uses PHPMailer SMTP if valid settings are configured in config/mail_settings.php.
 * Otherwise, falls back to PHP mail() and logs details locally.
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject line
 * @param string $body Email content (HTML support)
 * @return bool True if successfully sent or logged
 */
function send_mail($to, $subject, $body) {
    // 1. Clean parameters to avoid email header injection
    $to = filter_var(trim($to), FILTER_SANITIZE_EMAIL);
    $subject = str_replace(array("\r", "\n"), '', trim($subject));
    
    // 2. Load SMTP configurations
    $config_file = __DIR__ . '/../config/mail_settings.php';
    $config = [];
    if (file_exists($config_file)) {
        $config = require $config_file;
    }
    
    // Check if configuration is set and not using placeholder values
    $use_smtp = false;
    if (!empty($config['smtp_username']) && !empty($config['smtp_password']) && 
        $config['smtp_username'] !== 'your_gmail@gmail.com' && 
        $config['smtp_password'] !== 'your_google_app_password') {
        $use_smtp = true;
    }
    
    $mail_sent = false;
    $error_msg = '';
    
    if ($use_smtp) {
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $config['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['smtp_username'];
            $mail->Password   = $config['smtp_password'];
            $mail->SMTPSecure = ($config['smtp_secure'] === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $config['smtp_port'];
            
            // Disable SSL peer verification for local development SMTP compatibility
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Recipients
            $mail->setFrom($config['smtp_username'], $config['from_name']); // Set from to smtp_username for compatibility
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(html_entity_decode($body));
            
            $mail->send();
            $mail_sent = true;
        } catch (Exception $e) {
            $error_msg = $mail->ErrorInfo;
        }
    } else {
        // Fallback to PHP native mail()
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: MindCare Hub <no-reply@mindcare.edu>\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $mail_sent = @mail($to, $subject, $body, $headers);
        if (!$mail_sent) {
            $error_msg = 'PHP mail() function not configured / returned false.';
        }
    }
    
    // 3. Local Logging Fallback
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }
    
    $log_file = $log_dir . '/emails.log';
    $timestamp = date('Y-m-d H:i:s');
    
    $log_entry = "======================================================================\n";
    $log_entry .= "Date/Time:    $timestamp\n";
    $log_entry .= "Recipient:    $to\n";
    $log_entry .= "Subject:      $subject\n";
    if ($use_smtp) {
        $log_entry .= "Method:       SMTP (PHPMailer)\n";
        $log_entry .= "Status:       " . ($mail_sent ? "SUCCESS (Real Email Delivered)" : "FAILED (Error: $error_msg)") . "\n";
    } else {
        $log_entry .= "Method:       PHP mail()\n";
        $log_entry .= "Status:       " . ($mail_sent ? "SUCCESS" : "FAILED / NOT CONFIGURED (Local Development)") . "\n";
    }
    $log_entry .= "----------------------------------------------------------------------\n";
    
    $plain_text = html_entity_decode(strip_tags($body));
    $log_entry .= trim($plain_text) . "\n";
    $log_entry .= "======================================================================\n\n";
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
    return $mail_sent;
}
