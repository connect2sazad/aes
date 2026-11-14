<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit('CLI only');
$c=require dirname(__DIR__).'/config.php';
$db=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
// Additive, repeatable migration; keeps the original cover and every existing project.
$db->exec(file_get_contents(__DIR__.'/schema.sql'));
$db->exec("INSERT IGNORE INTO project_photos (project_id,photo,alt_text,sort_order) SELECT id,photo,title,0 FROM projects WHERE photo<>''");
echo "Upgrade complete. Existing cover photos preserved.\n";
