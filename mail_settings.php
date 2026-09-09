<?php
// config/mail_settings.php

return [
    'smtp_host'       => 'smtp.gmail.com',            // SMTP host (e.g. smtp.gmail.com)
    'smtp_port'       => 587,                         // SMTP port (e.g. 587 for TLS, 465 for SSL)
    'smtp_secure'     => 'tls',                       // Encryption: 'tls' or 'ssl'
    
    // Enter your real credentials below to send emails to your inbox
    'smtp_username'   => 'sreebalu01062005@gmail.com',      // Your Gmail or SMTP email address
    'smtp_password'   => 'yfxneiinscqbcjxq',  // Your 16-character Google App Password (not your normal password)
    
    'from_email'      => 'no-reply@mindcare.edu',     // Sender email address
    'from_name'       => 'MindCare Hub',              // Sender display name
];
