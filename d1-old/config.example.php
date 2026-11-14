<?php
// Copy to config.php. Keep this file outside the public document root.
return [
 'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=aanshu;charset=utf8mb4',
 'db_user' => 'aanshu_app', 'db_password' => 'CHANGE_ME',
 'production' => true,
 'base_url' => 'https://your-domain.example',
 'contact_email' => '',
 // Generate: php -r "echo bin2hex(random_bytes(32));"
 'app_key' => 'CHANGE_ME',
 // Optional: mail() must be configured by your hosting provider. Inbox always saves enquiries.
 'notify_email' => false,
];
