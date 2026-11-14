<?php
$source=dirname(__DIR__).'/public';
$url=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$prefix=str_starts_with($url,'/aes/d1/public/')?'/aes/d1/public':'';
$file=ltrim(substr($url,strlen($prefix)),'/');if($file==='')$file='index.php';
if(str_contains($file,'..') || str_contains($file,'\\')){http_response_code(404);return;}
if(preg_match('~^assets/[a-zA-Z0-9_.-]+$~D',$file) && is_file($source.'/'.$file)){
 $ext=pathinfo($file,PATHINFO_EXTENSION);header('Content-Type: '.(['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml','jpg'=>'image/jpeg'][$ext] ?? 'application/octet-stream'));readfile($source.'/'.$file);return;
}
if(!in_array($file,['index.php','admin.php','media.php','privacy.php','project.php','sitemap.php','robots.php'],true)){http_response_code(404);return;}
$_SERVER['SCRIPT_NAME']=$prefix.'/'.$file;$_SERVER['PHP_SELF']=$_SERVER['SCRIPT_NAME'];$_SERVER['SCRIPT_FILENAME']=$source.'/'.$file;require $source.'/'.$file;
