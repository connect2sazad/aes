<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
const ROOT = __DIR__ . '/..';
$config = is_file(ROOT.'/config.php') ? require ROOT.'/config.php' : null;
if (!$config || strlen($config['app_key'] ?? '') < 64) {
    http_response_code(503); exit('Website setup required. Follow README.md.');
}
if (($config['production'] ?? true) && ($_SERVER['HTTPS'] ?? '') !== 'on') {
    http_response_code(403); exit('HTTPS is required. Configure TLS at your web server.');
}
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('X-Frame-Options: DENY');
if ($config['production']) header('Strict-Transport-Security: max-age=31536000');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_name('aes_session');
if(!is_dir(ROOT.'/storage/sessions')) mkdir(ROOT.'/storage/sessions',0700,true);
session_save_path(ROOT.'/storage/sessions');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>(bool)$config['production'],'httponly'=>true,'samesite'=>'Lax']);
if(!session_start()) { http_response_code(503); exit('Secure session unavailable. Please try later.'); }
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function db(): PDO {
    static $db;
    global $config;
    return $db ??= new PDO($config['dsn'], $config['db_user'], $config['db_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
}
function query(string $sql, array $params=[]): PDOStatement { $q=db()->prepare($sql); $q->execute($params); return $q; }
function csrf(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function checkCsrf(): void {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Invalid request. Refresh the page and try again.'); }
}
function field(string $key, int $max, bool $required=true): string {
    $v=trim((string)($_POST[$key] ?? ''));
    if (($required && $v==='') || mb_strlen($v)>$max || str_contains($v,"\0")) throw new InvalidArgumentException('Check the '.str_replace('_',' ',$key).' field (maximum '.$max.' characters).');
    return $v;
}
function redirect(string $url): never { header('Location: '.$url, true,303); exit; }
function flash(string $message): void { $_SESSION['flash']=$message; }
function notice(): void { if(isset($_SESSION['flash'])) { echo '<div class="notice" role="status">'.e($_SESSION['flash']).'</div>'; unset($_SESSION['flash']); } }
function authenticated(): bool {
    if (empty($_SESSION['admin_id'])) return false;
    $hash=query('SELECT password_hash FROM admins WHERE id=?',[$_SESSION['admin_id']])->fetchColumn();
    if(!$hash || !hash_equals(hash('sha256',$hash),$_SESSION['password_fingerprint'] ?? '')) { unset($_SESSION['admin_id']); return false; }
    if (time()-($_SESSION['last_activity'] ?? 0)>1800 || time()-($_SESSION['login_at'] ?? 0)>28800) { unset($_SESSION['admin_id']); return false; }
    $_SESSION['last_activity']=time(); return true;
}
function requireAdmin(): void { if(!authenticated()) redirect('/admin.php'); }
// A database-backed fixed window works across PHP workers. No client-controlled proxy headers.
function rateLimit(string $action, int $max, int $seconds): void {
    global $config;
    $key=hash_hmac('sha256',$action.'|'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'),$config['app_key']);
    query('INSERT INTO rate_limits (bucket,hits,expires_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE hits=IF(expires_at < ?,1,hits+1), expires_at=IF(expires_at < ?,VALUES(expires_at),expires_at)',[$key,time()+$seconds,time(),time()]);
    if ((int)query('SELECT hits FROM rate_limits WHERE bucket=?',[$key])->fetchColumn()>$max) { http_response_code(429); header('Retry-After: '.$seconds); exit('Too many attempts. Please try again later.'); }
    if(random_int(1,100)===1) query('DELETE FROM rate_limits WHERE expires_at < ?',[time()-86400]);
}
function audit(string $action): void { query('INSERT INTO audit_log (admin_id,action,created_at) VALUES (?,?,?)',[$_SESSION['admin_id'] ?? null,$action,date('Y-m-d H:i:s')]); }
function content(): array {
    $out=[]; foreach(query('SELECT content_key,content_value FROM content')->fetchAll() as $row) $out[$row['content_key']]=$row['content_value']; return $out;
}
// Decode and re-encode accepted images: originals, metadata, names, and embedded payloads are discarded.
function uploadPhoto(): ?string {
    if (($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
    $f=$_FILES['photo'];
    if($f['error']!==UPLOAD_ERR_OK || $f['size']>5*1024*1024 || !is_uploaded_file($f['tmp_name'])) throw new InvalidArgumentException('Upload a JPG, PNG or WebP image smaller than 5 MB.');
    $info=@getimagesize($f['tmp_name']);
    if(!$info || !in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true) || $info[0]*$info[1]>20000000) throw new InvalidArgumentException('Unsupported image or dimensions exceed 20 megapixels.');
    $image=@imagecreatefromstring(file_get_contents($f['tmp_name']));
    if(!$image) throw new InvalidArgumentException('Cannot read image.');
    $w=min(1800,imagesx($image)); $h=(int)round(imagesy($image)*$w/imagesx($image));
    $clean=imagecreatetruecolor($w,$h); $white=imagecolorallocate($clean,255,255,255); imagefill($clean,0,0,$white);
    imagecopyresampled($clean,$image,0,0,0,0,$w,$h,imagesx($image),imagesy($image));
    $name=bin2hex(random_bytes(24)).'.jpg';
    if(!is_dir(ROOT.'/storage/photos')) mkdir(ROOT.'/storage/photos',0700,true);
    if(!imagejpeg($clean,ROOT.'/storage/photos/'.$name,88)) throw new RuntimeException('Photo could not be stored.');
    imagedestroy($image); imagedestroy($clean); return $name;
}
set_exception_handler(function(Throwable $error): void {
    error_log((string)$error); http_response_code(503);
    echo 'The service is temporarily unavailable. Please try again or contact us on +91 91149 59111.';
});
