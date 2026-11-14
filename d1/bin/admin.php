<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit('CLI only');
if(count($argv)!==2 || !filter_var($argv[1],FILTER_VALIDATE_EMAIL)) exit("Usage: php bin/admin.php owner@example.com\nPassword is read from stdin (minimum 14 characters).\n");
echo "Enter a unique password (input may be visible in your terminal): ";
$password=rtrim((string)fgets(STDIN),"\r\n");
if(strlen($password)<14 || strlen($password)>200) exit("Password must have 14–200 characters.\n");
$c=require dirname(__DIR__).'/config.php';
$db=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$q=$db->prepare('INSERT INTO admins (email,password_hash) VALUES (?,?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)');
$q->execute([strtolower($argv[1]),password_hash($password,PASSWORD_ARGON2ID)]);
// Password rotation invalidates all existing PHP sessions by requiring a matching hash fingerprint.
echo "Admin created/updated.\n";
