<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$c=require dirname(__DIR__).'/config.php';
if($c['production'] || !str_contains($c['dsn'],'dbname=aanshu_test') || ($c['social_test_base'] ?? '')!=='http://127.0.0.1:8091')exit("Requires disposable aanshu_test database and loopback provider simulator on port 8091.\n");
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/publisher.php';
$base='http://127.0.0.1:8086/aes/d1/public';$cookie=tempnam(sys_get_temp_dir(),'aessocial');$checks=0;$failed=false;$files=[];$postIds=[];
function hit(string $path,?array $data=null): array {
    global $base,$cookie;$headers='';$curl=curl_init($base.$path);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>30,CURLOPT_HEADERFUNCTION=>function($c,$h)use(&$headers){$headers.=$h;return strlen($h);}]);
    if($data!==null)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$data]);
    $body=curl_exec($curl);if($body===false)throw new RuntimeException('HTTP request failed');$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);return [$status,$body,$headers];
}
function tok(string $body): string {preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);return $m[1] ?? '';}
function ok(bool $result,string $label): void {global $checks;if(!$result)throw new RuntimeException($label);$checks++;echo 'PASS: '.$label.PHP_EOL;}
function testPost(string $title,?string $photo=null): int {global $postIds;query('INSERT INTO posts (title,body,photo,published) VALUES (?,?,?,1)',[$title,'Simulated publishing fixture. Not sent to a real account.',$photo]);$id=(int)db()->lastInsertId();$postIds[]=$id;return $id;}
function socialJob(int $post,string $channel): array {return query('SELECT * FROM social_jobs WHERE post_id=? AND channel=?',[$post,$channel])->fetch();}
$originalSeo=query('SELECT setting_key,setting_value FROM seo_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$originalProfiles=query('SELECT channel,profile_url FROM social_profiles')->fetchAll(PDO::FETCH_KEY_PAIR);
$originalAccounts=query('SELECT * FROM social_accounts')->fetchAll();
try {
    query('DELETE FROM rate_limits');query('DELETE FROM social_accounts');query('DELETE FROM social_profiles');
    [$status,$home]=hit('/');ok($status===200,'Nested homepage serves successfully');
    ok(str_contains($home,'/aes/d1/public/assets/style.css') && str_contains($home,'/aes/d1/public/privacy.php'),'Nested CSS and internal links retain installation prefix');
    ok(hit('/assets/style.css')[0]===200 && hit('/assets/site.js')[0]===200,'Nested CSS and JavaScript load');
    $dom=new DOMDocument();@$dom->loadHTML($home);$paths=[];
    foreach(['a'=>'href','img'=>'src','link'=>'href','script'=>'src','form'=>'action'] as $tag=>$attr)foreach($dom->getElementsByTagName($tag) as $node){$url=$node->getAttribute($attr);if(str_starts_with($url,'/'))$paths[]=$url;}
    ok(count(array_filter($paths,fn($p)=>!str_starts_with($p,'/aes/d1/public/')))===0,'Rendered homepage has no root-escaping local URL');
    $login=hit('/admin.php')[1];$loginToken=tok($login);
    [$status,,$headers]=hit('/admin.php',['csrf'=>$loginToken,'action'=>'login','email'=>'test@example.com','password'=>'LocalVerificationOnly!2026']);
    ok($status===303 && str_contains($headers,'Location: /aes/d1/public/admin.php'),'Login redirects to nested admin URL');
    ok(str_contains(strtolower($headers),'path=/aes/d1/public/'),'Session cookie is scoped to this installation');
    $csrf=tok(hit('/admin.php')[1]);
    foreach(['dashboard','content','projects','reviews','posts','enquiries','seo','social','help','security'] as $tab){[$status,$html]=hit('/admin.php?tab='.$tab);ok($status===200 && !str_contains($html,'temporarily unavailable'),'Nested admin tab renders: '.$tab);}
    $help=hit('/admin.php?tab=help')[1];ok(str_contains($help,'data-help-search') && str_contains($help,'data-print') && str_contains($help,'Installation checks'),'Help search, printable guide and installation checks are present');
    $seo=['csrf'=>$csrf,'action'=>'seo','title'=>'Nested SEO','description'=>'Nested site description','privacy_title'=>'Privacy','privacy_description'=>'Privacy description','canonical_url'=>'https://solar.example/aes/d1/public/','social_title'=>'','social_description'=>'','verification'=>'','indexing'=>1];
    [$status,,$headers]=hit('/admin.php?tab=seo',$seo);ok($status===303 && str_contains($headers,'Location: /aes/d1/public/admin.php?tab=seo'),'SEO saves successfully and keeps subfolder redirect');
    $home=hit('/')[1];ok(str_contains($home,'rel="canonical" href="https://solar.example/aes/d1/public/"'),'Canonical URL accepts and renders a subfolder');
    ok(str_contains(hit('/sitemap.php')[1],'https://solar.example/aes/d1/public/privacy.php') && str_contains(hit('/robots.php')[1],'/aes/d1/public/sitemap.php'),'Sitemap and crawler guidance retain public subfolder');
    $profiles=['csrf'=>$csrf,'action'=>'profiles','facebook'=>'https://www.facebook.com/example','instagram'=>'https://www.instagram.com/example/','linkedin'=>'https://www.linkedin.com/company/example/','youtube'=>'https://www.youtube.com/@example','x'=>'https://x.com/example'];
    ok(hit('/admin.php?tab=social',$profiles)[0]===303,'Five public social profiles save from admin');
    $home=hit('/')[1];ok(str_contains($home,'Facebook profile') && str_contains($home,'Instagram profile') && str_contains($home,'YouTube profile'),'Configured social links render publicly');
    $bad=$profiles;$bad['facebook']='https://facebook.com.evil.example/';ok(str_contains(hit('/admin.php?tab=social',$bad)[1],'valid HTTPS Facebook'),'Social links reject lookalike hosts');
    $bad['facebook']='javascript:alert(1)';ok(str_contains(hit('/admin.php?tab=social',$bad)[1],'valid HTTPS Facebook'),'Social links reject executable schemes');
    ok(hit('/admin.php?tab=social',['action'=>'profiles','csrf'=>'invalid'])[0]===403,'Social settings require valid CSRF');
    $secret='test_success';$account=['csrf'=>$csrf,'action'=>'account','channel'=>'facebook','account_id'=>'11111','access_token'=>$secret,'enabled'=>1];
    ok(hit('/admin.php?tab=social',$account)[0]===303,'Facebook publishing credentials save through admin');
    $cipher=query("SELECT token_cipher FROM social_accounts WHERE channel='facebook'")->fetchColumn();ok($cipher!==$secret && !str_contains($cipher,$secret) && decryptSocialToken($cipher)===$secret,'Access token encrypted at rest and decrypts only server-side');
    ok(!str_contains(hit('/admin.php?tab=social')[1],$secret),'Stored access token never rendered back to admin');
    $account['channel']='instagram';$account['account_id']='22222';hit('/admin.php?tab=social',$account);
    $textPost=testPost('TEST social text');
    $queue=['csrf'=>$csrf,'action'=>'queue','post_id'=>$textPost,'channel'=>'facebook'];
    ok(hit('/admin.php?tab=social',$queue)[0]===303,'Facebook job queues through admin');
    ok(str_contains(hit('/admin.php?tab=social',$queue)[1],'already has a job'),'Duplicate network job rejected');
    runSocialWorker();$job=socialJob($textPost,'facebook');ok($job['status']==='sent' && $job['remote_id']==='11111_22222','Real HTTP worker records Facebook text publication from simulated provider');
    $before=file_get_contents(ROOT.'/storage/meta-requests.jsonl');runSocialWorker();ok(file_get_contents(ROOT.'/storage/meta-requests.jsonl')===$before,'Sent job never resent by a later worker run');
    $name=bin2hex(random_bytes(24)).'.jpg';if(!is_dir(ROOT.'/storage/photos'))mkdir(ROOT.'/storage/photos',0700,true);$im=imagecreatetruecolor(1200,900);imagejpeg($im,ROOT.'/storage/photos/'.$name);imagedestroy($im);$files[]=$name;
    $imagePost=testPost('TEST social photo',$name);$queue['post_id']=$imagePost;$queue['channel']='instagram';
    ok(hit('/admin.php?tab=social',$queue)[0]===303,'Instagram photo job queues');
    runSocialWorker();$job=socialJob($imagePost,'instagram');ok($job['status']==='sent' && $job['container_id']==='44444' && $job['remote_id']==='55555','Instagram create/status/publish flow runs over real HTTP against simulated provider');
    $deniedPost=testPost('TEST social denied');query("UPDATE social_accounts SET token_cipher=? WHERE channel='facebook'",[encryptSocialToken('test_denied')]);queueSocialPost($deniedPost,'facebook');runSocialWorker();$job=socialJob($deniedPost,'facebook');ok($job['status']==='failed' && str_contains($job['error_message'],'190'),'Expired token produces actionable failed state');
    $uncertainPost=testPost('TEST social uncertain');query("UPDATE social_accounts SET token_cipher=? WHERE channel='facebook'",[encryptSocialToken('test_uncertain')]);queueSocialPost($uncertainPost,'facebook');runSocialWorker();$job=socialJob($uncertainPost,'facebook');ok($job['status']==='needs_review','Ambiguous provider write failure requires review');
    $before=file_get_contents(ROOT.'/storage/meta-requests.jsonl');runSocialWorker();ok(file_get_contents(ROOT.'/storage/meta-requests.jsonl')===$before,'Uncertain publication never automatically retried');
    ok(str_contains(hit('/admin.php?tab=social',['csrf'=>$csrf,'action'=>'retry','job_id'=>$job['id']])[1],'confirm that this post was not published'),'Retry requires explicit account verification');
    query("UPDATE social_accounts SET token_cipher=? WHERE channel='facebook'",[encryptSocialToken('test_success')]);
    ok(hit('/admin.php?tab=social',['csrf'=>$csrf,'action'=>'retry','job_id'=>$job['id'],'checked_account'=>'yes'])[0]===303,'Administrator can explicitly retry a checked job');runSocialWorker();ok(socialJob($uncertainPost,'facebook')['status']==='sent','Checked retry can complete after recovery');
    $processingPost=testPost('TEST social processing',$name);query("UPDATE social_accounts SET token_cipher=? WHERE channel='instagram'",[encryptSocialToken('test_processing')]);queueSocialPost($processingPost,'instagram');runSocialWorker();$job=socialJob($processingPost,'instagram');ok($job['status']==='pending' && $job['container_id']==='44444','Instagram processing retains container and defers publication');
    query("UPDATE social_accounts SET token_cipher=? WHERE channel='instagram'",[encryptSocialToken('test_success')]);query('UPDATE social_jobs SET available_at=? WHERE id=?',[date('Y-m-d H:i:s',time()-1),$job['id']]);$before=file_get_contents(ROOT.'/storage/meta-requests.jsonl');runSocialWorker();$new=substr(file_get_contents(ROOT.'/storage/meta-requests.jsonl'),strlen($before));ok(socialJob($processingPost,'instagram')['status']==='sent' && !str_contains($new,'22222\/media"'),'Deferred Instagram job resumes existing container without recreating it');
    $cancelledPost=testPost('TEST social cancelled');queueSocialPost($cancelledPost,'facebook');query('UPDATE posts SET published=0 WHERE id=?',[$cancelledPost]);runSocialWorker();ok(socialJob($cancelledPost,'facebook')['status']==='cancelled','Unpublished website post cancels pending social job');
    $deletedPost=testPost('TEST social deleted');queueSocialPost($deletedPost,'facebook');$deletedJob=socialJob($deletedPost,'facebook');query('DELETE FROM posts WHERE id=?',[$deletedPost]);runSocialWorker();ok(query('SELECT status FROM social_jobs WHERE id=?',[$deletedJob['id']])->fetchColumn()==='cancelled','Deleting website post preserves and cancels its publishing history');
    $noPhoto=testPost('TEST social no image');try{queueSocialPost($noPhoto,'instagram');$rejected=false;}catch(InvalidArgumentException $e){$rejected=true;}ok($rejected,'Instagram text-only job rejected before contacting provider');
    query("UPDATE seo_settings SET setting_value='http://localhost/aes/d1/public' WHERE setting_key='canonical_url'");try{socialMediaUrl($name);$rejected=false;}catch(InvalidArgumentException $e){$rejected=true;}ok($rejected,'Localhost image publishing blocked with setup guidance');
    [$status,,$headers]=hit('/',['csrf'=>$csrf,'name'=>'TEST nested enquiry','phone'=>'9999999999','email'=>'','service'=>'Rooftop solar','message'=>'Subfolder form test','consent'=>'yes']);ok($status===303 && str_contains($headers,'Location: /aes/d1/public/#contact'),'Public enquiry redirect retains subfolder and anchor');
    [$status,,$headers]=hit('/admin.php',['csrf'=>$csrf,'action'=>'logout']);ok($status===303 && str_contains($headers,'Location: /aes/d1/public/admin.php'),'Logout redirects within the installation');
    echo "\n$checks subfolder/social checks passed. Provider responses were simulated, not live Meta.\n";
} catch(Throwable $e) {$failed=true;echo 'FAIL: '.$e->getMessage().PHP_EOL;}
finally {
    foreach($postIds as $id)query('DELETE FROM posts WHERE id=?',[$id]);query("DELETE FROM social_jobs WHERE caption LIKE 'TEST social %'");query("DELETE FROM enquiries WHERE name='TEST nested enquiry'");query('DELETE FROM rate_limits');
    query('DELETE FROM seo_settings');foreach($originalSeo as $k=>$v)query('INSERT INTO seo_settings VALUES (?,?)',[$k,$v]);
    query('DELETE FROM social_profiles');foreach($originalProfiles as $k=>$v)query('INSERT INTO social_profiles VALUES (?,?)',[$k,$v]);
    query('DELETE FROM social_accounts');foreach($originalAccounts as $a)query('INSERT INTO social_accounts (channel,account_id,token_cipher,enabled) VALUES (?,?,?,?)',[$a['channel'],$a['account_id'],$a['token_cipher'],$a['enabled']]);
    foreach($files as $file)@unlink(ROOT.'/storage/photos/'.$file);@unlink($cookie);
}
exit($failed?1:0);
