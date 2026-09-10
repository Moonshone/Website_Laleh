<?php

// Copy this file to config.local.php for application/email settings only.
// Database credentials belong in config/database.local.php.
return [
    // Public HTTPS origin only, without a trailing slash.
    'APP_URL' => 'https://www.example.com',
    // A mailbox on your DreamHost-hosted domain. No browser receives this value.
    'MAIL_FROM' => 'website@example.com',
];
