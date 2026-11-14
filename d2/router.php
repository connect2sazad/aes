<?php
// Development server only. Apache production uses .htaccess.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/');
if($path==='/' || $path==='/index.php') { require __DIR__.'/index.php'; return true; }
if($path==='/admin.php') { require __DIR__.'/admin.php'; return true; }
if(preg_match('#^/assets/[a-zA-Z0-9_.-]+\.(css|js|jpg|svg)$#D',$path) && is_file(__DIR__.$path)) return false;
if(preg_match('#^/uploads/[a-f0-9]{40}\.jpg$#D',$path) && is_file(__DIR__.$path)) return false;
http_response_code(404); echo 'Page not found';
