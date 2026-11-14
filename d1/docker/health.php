<?php
try {
    $c=require '/var/www/html/config.php';
    $db=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>3]);
    $db->query('SELECT content_key FROM content LIMIT 1');
    if(!is_writable('/var/www/html/storage'))exit(1);
    exit(0);
} catch(Throwable $e){exit(1);}
