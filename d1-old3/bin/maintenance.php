<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit('CLI only');
$root=dirname(__DIR__);$c=require $root.'/config.php';
$db=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$q=$db->prepare('DELETE FROM rate_limits WHERE expires_at < ?');$q->execute([time()-86400]);
// Delete only closed enquiries older than 180 days. Review active enquiries manually.
$db->exec("DELETE FROM enquiries WHERE status='closed' AND created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");
$used=$db->query("SELECT photo FROM projects UNION SELECT photo FROM project_photos UNION SELECT photo FROM posts WHERE photo IS NOT NULL UNION SELECT photo FROM social_jobs WHERE photo IS NOT NULL AND status IN ('pending','processing','needs_review') UNION SELECT setting_value FROM seo_settings WHERE setting_key='social_photo'")->fetchAll(PDO::FETCH_COLUMN);
foreach(glob($root.'/storage/photos/*.jpg') ?: [] as $file) {
    if(!in_array(basename($file),$used,true) && filemtime($file)<time()-86400) unlink($file);
}
foreach(glob($root.'/storage/sessions/sess_*') ?: [] as $file) {
    if(filemtime($file)<time()-86400) unlink($file);
}
echo "Maintenance completed. Review open enquiries older than 180 days separately.\n";
