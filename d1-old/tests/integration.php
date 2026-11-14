<?php
declare(strict_types=1);
// Run only against an isolated database named *_test. This suite writes and deletes fixtures.
if(PHP_SAPI!=='cli') exit;
$root=dirname(__DIR__); $config=require $root.'/config.php';
if(!str_contains($config['dsn'],'dbname=aanshu_test') || $config['production']) exit("Refusing to run outside the isolated local test environment.\n");
$db=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('DELETE FROM rate_limits');
$base='http://127.0.0.1:8086'; $jar=tempnam(sys_get_temp_dir(),'aes'); $anonymous=tempnam(sys_get_temp_dir(),'aes'); $count=0;
function request(string $path, ?array $data=null, bool $auth=true): array {
    global $base,$jar,$anonymous;
    $c=curl_init($base.$path); $headers=[];
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$auth?$jar:$anonymous,CURLOPT_COOKIEJAR=>$auth?$jar:$anonymous,CURLOPT_TIMEOUT=>15,CURLOPT_HEADERFUNCTION=>function($c,$h)use(&$headers){$headers[]=$h;return strlen($h);}]);
    if($data!==null) {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$data);}
    $body=curl_exec($c); if($body===false) throw new RuntimeException(curl_error($c));
    $code=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);return [$code,$body,implode('',$headers)];
}
function token(string $body): string {preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);return $m[1]??'';}
function check(bool $condition,string $label): void {global $count;if(!$condition)throw new RuntimeException('FAIL: '.$label);$count++;echo "PASS: $label\n";}
try {
    [$code,$home,$headers]=request('/');
    check($code===200 && str_contains($home,'Aanshu Engineering'),'Public page renders against real MariaDB');
    check(str_contains($headers,"frame-ancestors 'none'") && str_contains($headers,'X-Content-Type-Options: nosniff'),'Security headers sent');
    check(str_contains($headers,'HttpOnly') && str_contains($headers,'SameSite=Lax'),'Session cookie flags');
    check(request('/config.php')[0]===404 && request('/bin/admin.php')[0]===404,'Document root excludes config and CLI scripts');
    check(request('/admin.php?tab=enquiries',null,false)[0]===200 && !str_contains(request('/admin.php?tab=enquiries',null,false)[1],'Recent activity'),'Unauthenticated admin renders only login');
    check(request('/admin.php',['action'=>'save','title'=>'attack'],false)[0]===403,'Unauthenticated and CSRF-free write rejected');
    $login=request('/admin.php')[1];
    check(request('/admin.php',['csrf'=>token($login),'action'=>'login','email'=>"' OR 1=1 --",'password'=>'bad'])[0]===200,'SQL injection cannot authenticate');
    [$code]=request('/admin.php',['csrf'=>token($login),'action'=>'login','email'=>'test@example.com','password'=>'LocalVerificationOnly!2026']);
    check($code===303,'Admin password login succeeds');
    $dashboard=request('/admin.php')[1]; $csrf=token($dashboard);
    check(str_contains($dashboard,'Website manager'),'Authenticated dashboard visible');
    check($csrf!==token($login),'CSRF token rotates after login');
    $storedContent=$db->query('SELECT content_key,content_value FROM content')->fetchAll(PDO::FETCH_KEY_PAIR);
    $copy=$storedContent;$copy['csrf']=$csrf;$copy['action']='content';$copy['hero_title']='TEST editable headline';$copy['email']='contact@example.com';
    check(request('/admin.php?tab=content',$copy)[0]===303,'Main page content saves atomically');
    $updated=request('/')[1];check(str_contains($updated,'TEST editable headline') && str_contains($updated,'mailto:contact@example.com'),'Content and email link reflect database update');
    $copy['phone']='javascript:alert(1)';
    check(str_contains(request('/admin.php?tab=content',$copy)[1],'Phone must include'),'Unsafe contact link value rejected');
    $anon=request('/admin.php',null,false)[1];
    check(request('/admin.php?tab=posts',['csrf'=>token($anon),'action'=>'save','title'=>'TEST unauthorized','body'=>'attack'],false)[0]===303,'Valid anonymous CSRF cannot bypass admin authentication');
    check(request('/admin.php?tab=posts',['csrf'=>'wrong','action'=>'save'])[0]===403,'Authenticated write requires valid CSRF');
    $payload='<script>alert("xss")</script>';
    check(request('/admin.php?tab=posts',['csrf'=>$csrf,'action'=>'save','id'=>0,'title'=>'TEST draft','body'=>$payload])[0]===303,'Draft post saved');
    $id=(int)$db->query("SELECT id FROM posts WHERE title='TEST draft' ORDER BY id DESC LIMIT 1")->fetchColumn();
    check(!str_contains(request('/')[1],'TEST draft'),'Draft not disclosed publicly');
    check(request('/admin.php?tab=posts',['csrf'=>$csrf,'action'=>'save','id'=>$id,'title'=>'TEST published','body'=>$payload,'published'=>1])[0]===303,'Post edit and publication succeeds');
    $html=request('/')[1];check(str_contains($html,'&lt;script&gt;') && !str_contains($html,$payload),'Stored script rendered as escaped text');
    $tmp=tempnam(sys_get_temp_dir(),'photo');$image=imagecreatetruecolor(80,60);imagejpeg($image,$tmp);imagedestroy($image);
    $project=['csrf'=>$csrf,'action'=>'save','id'=>0,'title'=>'TEST solar project','category'=>'Solar','location'=>'Test site','description'=>'Integration fixture','photo'=>new CURLFile($tmp,'image/jpeg','original.php.jpg')];
    check(request('/admin.php?tab=projects',$project)[0]===303,'Real multipart photo upload accepted and normalized');
    $p=$db->query("SELECT * FROM projects WHERE title='TEST solar project' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    check((bool)preg_match('/^[a-f0-9]{48}\.jpg$/D',$p['photo']),'Upload renamed to random JPEG');
    check(request('/media.php?file='.$p['photo'],null,false)[0]===404,'Draft image inaccessible to anonymous visitors');
    check(request('/media.php?file='.$p['photo'])[0]===200,'Admin can preview draft image');
    unset($project['photo']);$project['id']=$p['id'];$project['published']=1;
    request('/admin.php?tab=projects',$project);
    check(request('/media.php?file='.$p['photo'],null,false)[0]===200,'Published photo publicly available');
    file_put_contents($tmp,'<?php echo "attack";');$project['photo']=new CURLFile($tmp,'image/jpeg','bad.jpg');
    check(str_contains(request('/admin.php?tab=projects',$project)[1],'Unsupported image'),'Disguised executable upload rejected');
    check(request('/media.php?file=..%2Fconfig.php')[0]===404,'Media path traversal rejected');
    check(request('/admin.php?tab=reviews',['csrf'=>$csrf,'action'=>'save','id'=>0,'customer'=>'TEST customer','location'=>'Test','quote'=>'Approved fixture','rating'=>'5','published'=>1,'permission'=>'on'])[0]===303,'Review publication succeeds');
    check(str_contains(request('/')[1],'TEST customer'),'Published review visible');
    [$code,$body]=request('/'); $form=token($body);
    $enquiry=['csrf'=>$form,'name'=>'TEST enquiry','phone'=>'9999999999','email'=>'test@example.com','service'=>'Rooftop solar','message'=>$payload,'consent'=>'yes'];
    check(request('/',$enquiry)[0]===303,'Visitor enquiry saves to database');
    $q=$db->query("SELECT * FROM enquiries WHERE name='TEST enquiry' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    check($q && $q['notification_status']==='not_configured','Enquiry persisted with honest email state');
    check(str_contains(request('/admin.php?tab=enquiries')[1],'&lt;script&gt;'),'Enquiry inbox escapes visitor text');
    $bad=$enquiry;unset($bad['consent']);
    check(str_contains(request('/',$bad)[1],'Please consent'),'Consent enforced on server');
    $bad=$enquiry;$bad['email']='bad-email';
    check(str_contains(request('/',$bad)[1],'valid email'),'Invalid email rejected on server');
    $bad=$enquiry;$bad['website']='spam';
    check(str_contains(request('/',$bad)[1],'Unable to submit'),'Honeypot blocks spam submission');
    request('/',$enquiry);check(request('/',$enquiry)[0]===429,'Public enquiry rate limit enforced');
    check(request('/admin.php?tab=enquiries',['csrf'=>$csrf,'action'=>'status','id'=>$q['id'],'status'=>'contacted'])[0]===303,'Enquiry follow-up status updates');
    check(request('/admin.php?tab=enquiries',['csrf'=>$csrf,'action'=>'delete','id'=>$q['id']])[0]===303,'Enquiry deletion succeeds');
    check(request('/admin.php?tab=posts',['csrf'=>$csrf,'action'=>'delete','id'=>$id])[0]===303,'Post deletion succeeds');
    request('/admin.php',['csrf'=>$csrf,'action'=>'logout']);
    check(!str_contains(request('/admin.php')[1],'Website manager'),'Logout revokes access');
    $login=request('/admin.php')[1];
    for($n=0;$n<9;$n++) $result=request('/admin.php',['csrf'=>token($login),'action'=>'login','email'=>'wrong@example.com','password'=>'bad']);
    check($result[0]===429,'Login brute-force throttling enforced');
    echo "\n$count checks passed on real local MariaDB and HTTP.\n";
} finally {
    $db->exec("DELETE FROM posts WHERE title LIKE 'TEST %'; DELETE FROM reviews WHERE customer LIKE 'TEST %'; DELETE FROM projects WHERE title LIKE 'TEST %'; DELETE FROM enquiries WHERE name LIKE 'TEST %'; DELETE FROM rate_limits;");
    if(isset($p['photo'])) @unlink($root.'/storage/photos/'.$p['photo']);
    if(isset($tmp)) @unlink($tmp);@unlink($jar);@unlink($anonymous);
    if(isset($storedContent)) {
        $restore=$db->prepare('UPDATE content SET content_value=? WHERE content_key=?');
        foreach($storedContent as $key=>$value) $restore->execute([$value,$key]);
    }
}
