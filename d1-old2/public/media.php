<?php
require dirname(__DIR__).'/app/bootstrap.php';
$name=(string)($_GET['file'] ?? '');
if(!preg_match('/^[a-f0-9]{48}\.jpg$/D',$name)) { http_response_code(404); exit; }
$public=query("SELECT pp.id FROM project_photos pp JOIN projects p ON p.id=pp.project_id WHERE pp.photo=? AND p.published=1 UNION ALL SELECT id FROM posts WHERE photo=? AND published=1 UNION ALL SELECT 0 FROM seo_settings WHERE setting_key='social_photo' AND setting_value=? LIMIT 1",[$name,$name,$name])->fetch();
if(!$public && !authenticated()) { http_response_code(404); exit; }
$path=ROOT.'/storage/photos/'.$name;
if(!is_file($path)) {http_response_code(404);exit;}
session_write_close(); header('Content-Type: image/jpeg'); header('Cache-Control: private, no-store'); header('Content-Length: '.filesize($path)); readfile($path);
