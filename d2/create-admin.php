<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/bootstrap.php';
echo "Admin email: "; $email=trim(fgets(STDIN));
if(!filter_var($email,FILTER_VALIDATE_EMAIL)) exit("Invalid email\n");
echo "Password (at least 14 characters; terminal input is visible): "; $password=trim(fgets(STDIN));
if(strlen($password)<14 || strlen($password)>72) exit("Use 14 to 72 bytes for the password\n");
$q=db()->prepare('INSERT INTO admins(email,password_hash) VALUES (?,?)');
$q->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);
echo "Admin created.\n";
