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
    check(request('/project.php?id='.$p['id'],null,false)[0]===404,'Draft project detail page remains private');
    check(!str_contains(request('/sitemap.php')[1],'/project.php?id='.$p['id'].'<'),'Draft project excluded from sitemap');
    check(request('/media.php?file='.$p['photo'])[0]===200,'Admin can preview draft image');
    unset($project['photo']);$project['id']=$p['id'];$project['published']=1;
    request('/admin.php?tab=projects',$project);
    check(request('/media.php?file='.$p['photo'],null,false)[0]===200,'Published photo publicly available');
    $multi=$project;$multi['photos[0]']=new CURLFile($tmp,'image/jpeg','first.jpg');$multi['photos[1]']=new CURLFile($tmp,'image/jpeg','second.jpg');
    check(request('/admin.php?tab=projects',$multi)[0]===303,'Two photos uploaded in one request and appended');
    $photos=$db->query('SELECT * FROM project_photos WHERE project_id='.(int)$p['id'].' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $allTestPhotos=array_column($photos,'photo');
    check(count($photos)===3,'Original photo preserved alongside new photos');
    foreach($photos as $image) check(request('/media.php?file='.$image['photo'],null,false)[0]===200,'Published gallery photo publicly accessible');
    $manage=$project;$manage['cover_photo']=$photos[2]['id'];$manage['alt['.$photos[2]['id'].']']='Solar panels on a test roof';
    check(request('/admin.php?tab=projects',$manage)[0]===303,'Cover selection and photo alt text saved');
    check($db->query('SELECT photo FROM projects WHERE id='.(int)$p['id'])->fetchColumn()===$photos[2]['photo'],'Selected photo becomes project cover');
    $detail=request('/project.php?id='.$p['id'])[1];
    check(substr_count($detail,'loading="lazy"')===3 && str_contains($detail,'alt="Solar panels on a test roof"'),'Project detail renders every photo and saved alt text');
    $manage['remove_photos[0]']=$photos[0]['id'];
    check(request('/admin.php?tab=projects',$manage)[0]===303,'Individual photo can be removed');
    check(request('/media.php?file='.$photos[0]['photo'],null,false)[0]===404,'Removed image immediately unavailable to public');
    $manage['remove_photos[1]']=$photos[1]['id'];$manage['remove_photos[2]']=$photos[2]['id'];unset($manage['remove_photos[0]']);
    check(str_contains(request('/admin.php?tab=projects',$manage)[1],'1–12 photos'),'Removing all project photos is rejected');
    $manage=$project;$manage['cover_photo']='999999999';
    check(str_contains(request('/admin.php?tab=projects',$manage)[1],'Invalid cover photo'),'Foreign cover IDs rejected');
    $manage=$project;$manage['remove_photos[0]']='999999999';
    check(str_contains(request('/admin.php?tab=projects',$manage)[1],'does not belong'),'Foreign photo removals rejected');
    $manage=$project;for($i=0;$i<13;$i++) $manage['photos['.$i.']']=new CURLFile($tmp,'image/jpeg','test.jpg');
    check(str_contains(request('/admin.php?tab=projects',$manage)[1],'up to 12'),'Oversized photo batch rejected');
    $storedSeo=$db->query('SELECT setting_key,setting_value FROM seo_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    $seo=['csrf'=>$csrf,'action'=>'seo','title'=>'TEST SEO <script>','description'=>'Search description & solar','privacy_title'=>'Privacy TEST','privacy_description'=>'Privacy description','canonical_url'=>'https://solar.example/','social_title'=>'Solar sharing title','social_description'=>'Solar social description','verification'=>'test_token-123','indexing'=>1,'photo'=>new CURLFile($tmp,'image/jpeg','social.jpg')];
    check(request('/admin.php?tab=seo',$seo)[0]===303,'SEO text, indexing, verification and social image saved');
    $socialPhoto=$db->query("SELECT setting_value FROM seo_settings WHERE setting_key='social_photo'")->fetchColumn();$allTestPhotos[]=$socialPhoto;
    $home=request('/')[1];
    check(str_contains($home,'<title>TEST SEO &lt;script&gt;</title>') && str_contains($home,'name="description" content="Search description &amp; solar"'),'SEO title and description escaped in real page head');
    check(str_contains($home,'rel="canonical" href="https://solar.example/"'),'Canonical URL rendered');
    check(str_contains($home,'property="og:title" content="Solar sharing title"') && str_contains($home,'name="twitter:card"'),'Social sharing metadata rendered');
    check(str_contains($home,'name="google-site-verification" content="test_token-123"'),'Search Console verification rendered');
    check(request('/media.php?file='.$socialPhoto,null,false)[0]===200,'SEO sharing image publicly retrievable');
    check(str_contains(request('/privacy.php')[1],'<title>Privacy TEST</title>'),'Privacy page SEO applied separately');
    $sitemap=request('/sitemap.php')[1];check((bool)simplexml_load_string($sitemap) && str_contains($sitemap,'https://solar.example/project.php?id='.$p['id']),'Valid sitemap includes published project URL');
    check(str_contains(request('/robots.php')[1],'Sitemap: https://solar.example/sitemap.php'),'Crawler guidance uses configured canonical domain');
    unset($seo['photo'],$seo['indexing']);
    request('/admin.php?tab=seo',$seo);
    check(str_contains(request('/')[1],'name="robots" content="noindex,nofollow"') && str_contains(request('/project.php?id='.$p['id'])[1],'noindex,nofollow'),'Disable indexing applies to homepage and project pages');
    check(!str_contains(request('/sitemap.php')[1],'<url>'),'Index-disabled sitemap contains no URLs');
    $badSeo=$seo;$badSeo['canonical_url']='javascript:alert(1)';
    check(str_contains(request('/admin.php?tab=seo',$badSeo)[1],'full public website URL'),'Unsafe canonical scheme rejected');
    $badSeo=$seo;$badSeo['verification']='<script>alert(1)</script>';
    check(str_contains(request('/admin.php?tab=seo',$badSeo)[1],'only the Google verification token'),'HTML verification injection rejected');
    check(request('/admin.php?tab=seo',['action'=>'seo','csrf'=>'wrong'])[0]===403,'SEO mutations require valid CSRF');
    file_put_contents($tmp,'<?php echo "attack";');$project['photo']=new CURLFile($tmp,'image/jpeg','bad.jpg');
    check(str_contains(request('/admin.php?tab=projects',$project)[1],'Unsupported image'),'Disguised executable upload rejected');
    // A valid image followed by an invalid image must not leave a partial gallery or file.
    $beforeFiles=glob($root.'/storage/photos/*.jpg');
    $validTmp=tempnam(sys_get_temp_dir(),'aesgood');$im=imagecreatetruecolor(10,10);imagejpeg($im,$validTmp);imagedestroy($im);
    unset($project['photo']);$project['photos[0]']=new CURLFile($validTmp,'image/jpeg','good.jpg');$project['photos[1]']=new CURLFile($tmp,'image/jpeg','bad.jpg');
    check(str_contains(request('/admin.php?tab=projects',$project)[1],'Unsupported image') && $beforeFiles===glob($root.'/storage/photos/*.jpg'),'Mixed valid/invalid batch rolls back files and database');
    @unlink($validTmp);
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
    foreach($allTestPhotos ?? [] as $photo) @unlink($root.'/storage/photos/'.$photo);
    if(isset($storedSeo)) {
        $db->exec('DELETE FROM seo_settings');$restoreSeo=$db->prepare('INSERT INTO seo_settings (setting_key,setting_value) VALUES (?,?)');
        foreach($storedSeo as $key=>$value) $restoreSeo->execute([$key,$value]);
    }
    if(isset($tmp)) @unlink($tmp);@unlink($jar);@unlink($anonymous);
    if(isset($storedContent)) {
        $restore=$db->prepare('UPDATE content SET content_value=? WHERE content_key=?');
        foreach($storedContent as $key=>$value) $restore->execute([$value,$key]);
    }
}

