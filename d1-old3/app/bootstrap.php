<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
const ROOT = __DIR__ . '/..';
require __DIR__.'/urls.php';
$config = is_file(ROOT.'/config.php') ? require ROOT.'/config.php' : null;
if (!$config || strlen($config['app_key'] ?? '') < 64) {
    http_response_code(503); exit('Website setup required. Follow README.md.');
}
if (PHP_SAPI!=='cli' && ($config['production'] ?? true) && ($_SERVER['HTTPS'] ?? '') !== 'on') {
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
session_name('aes_'.substr(hash('sha256',(string)realpath(ROOT)),0,12));
if(!is_dir(ROOT.'/storage/sessions')) mkdir(ROOT.'/storage/sessions',0700,true);
session_save_path(ROOT.'/storage/sessions');
session_set_cookie_params(['lifetime'=>0,'path'=>appBasePath().'/','secure'=>(bool)$config['production'],'httponly'=>true,'samesite'=>'Lax']);
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
function redirect(string $url): never { header('Location: '.appUrl($url), true,303); exit; }
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
function tableExists(string $name): bool {
    return (int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$name])->fetchColumn()===1;
}
// Decode and re-encode accepted images: originals, metadata, names, and embedded payloads are discarded.
function uploadPhoto(?array $f=null): ?string {
    $f ??= $_FILES['photo'] ?? ['error'=>UPLOAD_ERR_NO_FILE];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
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
function projectPhotos(int $id): array {
    return query('SELECT pp.* FROM project_photos pp JOIN projects p ON p.id=pp.project_id WHERE project_id=? ORDER BY (pp.photo=p.photo) DESC,sort_order,pp.id',[$id])->fetchAll();
}
function uploadedProjectPhotos(): array {
    if(!isset($_FILES['photos'])) return isset($_FILES['photo']) && $_FILES['photo']['error']!==UPLOAD_ERR_NO_FILE?[$_FILES['photo']]:[];
    $files=$_FILES['photos'];
    if(!is_array($files['error'] ?? null) || count($files['error'])>12) throw new InvalidArgumentException('Choose up to 12 project photos.');
    $out=[];
    foreach($files['error'] as $i=>$error) {
        if($error===UPLOAD_ERR_NO_FILE) continue;
        $out[]=['error'=>$error,'size'=>$files['size'][$i], 'tmp_name'=>$files['tmp_name'][$i]];
    }
    return $out;
}
function seoSettings(): array {
    $s=['title'=>'Aanshu Engineering Solution | Solar & Electrical Infrastructure in Odisha',
        'description'=>'Solar rooftop supply and associated services across Odisha. 11 kV / 33 kV electrical lines and distribution transformer installation.',
        'privacy_title'=>'Privacy | Aanshu Engineering','privacy_description'=>'How Aanshu Engineering handles website enquiries and contact details.',
        'canonical_url'=>'','social_title'=>'','social_description'=>'','social_photo'=>'','indexing'=>'1','verification'=>''];
    if(tableExists('seo_settings')) foreach(query('SELECT setting_key,setting_value FROM seo_settings')->fetchAll() as $r) if(array_key_exists($r['setting_key'],$s)) $s[$r['setting_key']]=$r['setting_value'];
    return $s;
}
function validateSiteUrl(string $url): bool {
    global $config;
    $p=parse_url($url);
    return filter_var($url,FILTER_VALIDATE_URL)!==false && in_array($p['scheme'] ?? '',$config['production']?['https']:['http','https'],true)
        && !isset($p['user'],$p['pass']) && !isset($p['user']) && !isset($p['pass'])
        && !isset($p['query']) && !isset($p['fragment']) && !preg_match('~[\\\\]|(?:^|/)\.\.(?:/|$)~',$p['path'] ?? '');
}
function siteUrl(array $seo): string {
    global $config;
    $url=$seo['canonical_url'] ?: ($config['base_url'] ?? '');
    return validateSiteUrl($url)?rtrim($url,'/'):'';
}
function renderSeo(string $page='home', ?array $project=null): void {
    $s=seoSettings();$base=siteUrl($s);
    $title=$page==='privacy'?$s['privacy_title']:$s['title'];
    $description=$page==='privacy'?$s['privacy_description']:$s['description'];
    $url=$base.($page==='privacy'?'/privacy.php':'/');
    if($project) { $title=$project['title'].' | Aanshu Engineering';$description=mb_substr($project['description'],0,200);$url=$base.'/project.php?id='.(int)$project['id']; }
    $socialTitle=$page==='home' && $s['social_title']?$s['social_title']:$title;
    $socialDescription=$page==='home' && $s['social_description']?$s['social_description']:$description;
    $photo=$project['photo'] ?? ($page==='home'?$s['social_photo']:'');
    $image=$base.($photo?'/media.php?file='.rawurlencode($photo):'/assets/logo.jpg');
    echo '<title>'.e($title).'</title><meta name="description" content="'.e($description).'">';
    $robots=$s['indexing']==='1'?'index,follow':'noindex,nofollow';
    echo '<meta name="robots" content="'.$robots.'">';
    if($base) echo '<link rel="canonical" href="'.e($url).'">';
    foreach(['type'=>'website','locale'=>'en_IN','site_name'=>'Aanshu Engineering Solution Private Limited','title'=>$socialTitle,'description'=>$socialDescription] as $key=>$value) echo '<meta property="og:'.$key.'" content="'.e($value).'">';
    if($base) echo '<meta property="og:url" content="'.e($url).'"><meta property="og:image" content="'.e($image).'">';
    echo '<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="'.e($socialTitle).'"><meta name="twitter:description" content="'.e($socialDescription).'">';
    if($base) echo '<meta name="twitter:image" content="'.e($image).'">';
    if($page==='home' && $s['verification']) echo '<meta name="google-site-verification" content="'.e($s['verification']).'">';
}
require __DIR__.'/projects.php';
require __DIR__.'/social.php';
set_exception_handler(function(Throwable $error): void {
    error_log((string)$error); http_response_code(503);
    echo 'The service is temporarily unavailable. Please try again or contact us on +91 91149 59111.';
});
