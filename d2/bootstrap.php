<?php
declare(strict_types=1);
ini_set('display_errors', '0');
$config = is_file(__DIR__.'/config.local.php') ? require __DIR__.'/config.local.php' : [];
header("Content-Security-Policy: default-src 'self'; img-src 'self' https://images.unsplash.com data:; style-src 'self'; script-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('X-Frame-Options: DENY');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=31536000');
session_name('aes_session');
session_set_cookie_params(['httponly'=>true,'secure'=>$config['secure_cookies'] ?? true,'samesite'=>'Strict','path'=>'/']);
ini_set('session.use_strict_mode','1');
$sessionPath=__DIR__.'/storage/sessions';
if(!is_dir($sessionPath) && !mkdir($sessionPath,0700,true) && !is_dir($sessionPath)) { http_response_code(503); exit('Session storage unavailable.'); }
session_save_path($sessionPath);
if(!session_start()) { http_response_code(503); exit('Session storage unavailable.'); }
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function db(): PDO {
 global $config;
 static $pdo;
 if (!$pdo) {
  if (empty($config['dsn'])) throw new RuntimeException('Database not configured');
  $pdo = new PDO($config['dsn'], $config['db_user'], $config['db_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 }
 return $pdo;
}
function rows(string $table): array {
 if (!in_array($table,['projects','reviews','posts','enquiries'],true)) throw new RuntimeException('Invalid table');
 try { return db()->query("SELECT * FROM $table ORDER BY id DESC LIMIT 100")->fetchAll(); } catch (Throwable $ex) { return []; }
}
function content(string $key,string $fallback): string {
 try { $q=db()->prepare('SELECT body FROM content WHERE name=?'); $q->execute([$key]); return $q->fetchColumn() ?: $fallback; } catch(Throwable $ex) { return $fallback; }
}
function csrf(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function checkCsrf(): void { if (!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))) { http_response_code(403); exit('Invalid request. Reload the page and try again.'); } }
function field(string $key,int $max,bool $required=true): string {
 $value=trim((string)($_POST[$key]??''));
 if (($required && $value==='') || strlen($value)>$max) throw new RuntimeException('Check the '.str_replace('_',' ',$key).' field.');
 return $value;
}
function rateLimit(string $bucket,int $max,int $window): void {
 $dir=__DIR__.'/storage';
 $path=$dir.'/'.hash('sha256',$bucket.'|'.($_SERVER['REMOTE_ADDR']??'unknown')).'.json';
 $f=fopen($path,'c+');
 if (!$f || !flock($f,LOCK_EX)) throw new RuntimeException('Please try again later.');
 $data=json_decode(stream_get_contents($f),true) ?: ['at'=>time(),'count'=>0];
 if(time()-$data['at']>$window) $data=['at'=>time(),'count'=>0];
 $data['count']++;
 ftruncate($f,0); rewind($f); fwrite($f,json_encode($data)); fflush($f); flock($f,LOCK_UN); fclose($f);
 if($data['count']>$max) throw new RuntimeException('Too many attempts. Please try again later.');
}
function isAdmin(): bool { return isset($_SESSION['admin']) && time()-($_SESSION['active']??0)<1800; }
