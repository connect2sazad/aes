<?php
function socialChannels(): array {return ['facebook'=>'Facebook','instagram'=>'Instagram','linkedin'=>'LinkedIn','youtube'=>'YouTube','x'=>'X'];}
function socialProfiles(): array {return tableExists('social_profiles')?query('SELECT channel,profile_url FROM social_profiles')->fetchAll(PDO::FETCH_KEY_PAIR):[];}
function validSocialUrl(string $channel,string $url): bool {
    $hosts=['facebook'=>['facebook.com','www.facebook.com','m.facebook.com'],'instagram'=>['instagram.com','www.instagram.com'],'linkedin'=>['linkedin.com','www.linkedin.com'],'youtube'=>['youtube.com','www.youtube.com','youtu.be'],'x'=>['x.com','www.x.com','twitter.com','www.twitter.com']];
    $p=parse_url($url);
    return filter_var($url,FILTER_VALIDATE_URL)!==false && ($p['scheme'] ?? '')==='https' && in_array(strtolower($p['host'] ?? ''),$hosts[$channel] ?? [],true) && !isset($p['user']) && !isset($p['pass']) && !isset($p['port']);
}
function renderSocialLinks(): void {
    $profiles=socialProfiles();$channels=socialChannels();
    if(!$profiles) return;
    echo '<nav class="social-links" aria-label="Follow Aanshu Engineering">';
    foreach($channels as $key=>$label) if(!empty($profiles[$key]) && validSocialUrl($key,$profiles[$key])) echo '<a href="'.e($profiles[$key]).'" target="_blank" rel="noopener noreferrer" aria-label="'.e($label).' profile">'.e($label).' ↗</a>';
    echo '</nav>';
}
// Tokens are encrypted at rest, never placed in HTML, cookies, job rows or logs.
function encryptSocialToken(string $token): string {
    global $config;$iv=random_bytes(12);$tag='';
    $cipher=openssl_encrypt($token,'aes-256-gcm',hash('sha256',$config['app_key'],true),OPENSSL_RAW_DATA,$iv,$tag,'aes-social-v1');
    if($cipher===false) throw new RuntimeException('Could not encrypt account token.');
    return base64_encode($iv.$tag.$cipher);
}
function decryptSocialToken(string $value): string {
    global $config;$raw=base64_decode($value,true);
    if($raw===false || strlen($raw)<29) throw new RuntimeException('Account token must be configured again.');
    $token=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',$config['app_key'],true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'aes-social-v1');
    if($token===false) throw new RuntimeException('Account token cannot be decrypted. Reconfigure this account.');
    return $token;
}
function queueSocialPost(int $postId,string $channel): void {
    if(!in_array($channel,['facebook','instagram'],true)) throw new InvalidArgumentException('Choose Facebook or Instagram.');
    db()->beginTransaction();
    try {
        $post=query('SELECT * FROM posts WHERE id=? FOR UPDATE',[$postId])->fetch();
        if(!$post || !$post['published']) throw new InvalidArgumentException('Publish the website post before queueing it to social accounts.');
        if(!empty($post['demo_key'])) throw new InvalidArgumentException('Demo posts cannot be published to real social accounts. Create a genuine company post instead.');
        $account=query('SELECT * FROM social_accounts WHERE channel=? AND enabled=1',[$channel])->fetch();
        if(!$account) throw new InvalidArgumentException('Configure and enable this publishing account in Social accounts first.');
        if(query('SELECT id FROM social_jobs WHERE post_id=? AND channel=?',[$postId,$channel])->fetch()) throw new InvalidArgumentException('This post already has a job for that network. Check its status before retrying.');
        $caption=$post['title']."\n\n".$post['body'];
        if(mb_strlen($caption)>($channel==='instagram'?2200:5000)) throw new InvalidArgumentException('Shorten the title and caption before queueing (Instagram: 2,200 characters total; Facebook: 5,000 in this app).');
        if($channel==='instagram') {
            if(!$post['photo']) throw new InvalidArgumentException('Instagram requires a photo.');
            $info=@getimagesize(ROOT.'/storage/photos/'.$post['photo']);$ratio=$info?$info[0]/$info[1]:0;
            if(!$info || $ratio<0.8 || $ratio>1.91) throw new InvalidArgumentException('Use an Instagram photo with aspect ratio between 4:5 and 1.91:1.');
        }
        // Validate the public URL now; the worker validates it again when sending.
        socialMediaUrl($post['photo'] ?: null);
        query('INSERT INTO social_jobs (post_id,channel,account_id,caption,photo,available_at) VALUES (?,?,?,?,?,?)',[$postId,$channel,$account['account_id'],$caption,$post['photo'],date('Y-m-d H:i:s')]);
        audit('Queued '.$channel.' post #'.$postId);db()->commit();
    } catch(Throwable $e) {if(db()->inTransaction())db()->rollBack();throw $e;}
}
function socialMediaUrl(?string $photo): ?string {
    if(!$photo) return null;
    $url=siteUrl(seoSettings());$host=parse_url($url,PHP_URL_HOST);
    if(parse_url($url,PHP_URL_SCHEME)!=='https' || !$host || !str_contains($host,'.') || in_array($host,['localhost','127.0.0.1','::1'],true) || str_ends_with($host,'.localhost') || str_ends_with($host,'.local') || (filter_var($host,FILTER_VALIDATE_IP) && !filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) throw new InvalidArgumentException('Photo publishing needs a public HTTPS website URL in SEO settings. Meta cannot fetch localhost photos.');
    return rtrim($url,'/').'/media.php?file='.rawurlencode($photo);
}
function handleSocialAction(string $action): void {
    if($action==='profiles') {
        $values=[];foreach(socialChannels() as $key=>$label) {$url=field($key,500,false);if($url!=='' && !validSocialUrl($key,$url))throw new InvalidArgumentException('Enter a valid HTTPS '.$label.' profile URL.');$values[$key]=$url;}
        db()->beginTransaction();foreach($values as $key=>$url) {if($url==='') query('DELETE FROM social_profiles WHERE channel=?',[$key]);else query('INSERT INTO social_profiles (channel,profile_url) VALUES (?,?) ON DUPLICATE KEY UPDATE profile_url=VALUES(profile_url)',[$key,$url]);}audit('Updated public social profiles');db()->commit();flash('Social profile links saved.');
    } elseif($action==='account') {
        $channel=field('channel',30);if(!in_array($channel,['facebook','instagram'],true))throw new InvalidArgumentException('Unsupported account.');
        $id=field('account_id',80);if(!preg_match('/^[0-9]{5,80}$/D',$id))throw new InvalidArgumentException('Enter the numeric Page or Instagram professional account ID.');
        $existing=query('SELECT * FROM social_accounts WHERE channel=?',[$channel])->fetch();$token=field('access_token',4000,false);
        if($token==='' && (!$existing || $existing['account_id']!==$id))throw new InvalidArgumentException('A new or changed account needs an access token.');
        if($token!=='' && !preg_match('/^[A-Za-z0-9_.|\-]+$/D',$token))throw new InvalidArgumentException('Access token contains unsupported characters.');
        $cipher=$token!==''?encryptSocialToken($token):$existing['token_cipher'];
        query('INSERT INTO social_accounts (channel,account_id,token_cipher,enabled) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE account_id=VALUES(account_id),token_cipher=VALUES(token_cipher),enabled=VALUES(enabled)',[$channel,$id,$cipher,isset($_POST['enabled'])?1:0]);audit('Configured '.$channel.' publishing account');flash('Account saved. Credentials have not been verified with Meta.');
    } elseif($action==='disconnect') {
        $channel=field('channel',30);query('DELETE FROM social_accounts WHERE channel=?',[$channel]);query("UPDATE social_jobs SET status='cancelled',error_message='Account disconnected' WHERE channel=? AND status='pending'",[$channel]);audit('Disconnected '.$channel);flash('Publishing account disconnected. Public profile link is unchanged.');
    } elseif($action==='queue') {
        $id=filter_var($_POST['post_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id)throw new InvalidArgumentException('Invalid post.');queueSocialPost($id,field('channel',30));flash('Post queued. The scheduled worker will process it.');
    } elseif(in_array($action,['retry','cancel','resolve'],true)) {
        $id=filter_var($_POST['job_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id)throw new InvalidArgumentException('Invalid publishing job.');
        if($action==='cancel') query("UPDATE social_jobs SET status='cancelled',error_message='Cancelled by administrator' WHERE id=? AND status IN ('pending','failed')",[$id]);
        if($action==='retry') {if(($_POST['checked_account'] ?? '')!=='yes')throw new InvalidArgumentException('Check the real social account and confirm that this post was not published before retrying.');query("UPDATE social_jobs SET status='pending',error_message=NULL,available_at=? WHERE id=? AND status IN ('failed','needs_review')",[date('Y-m-d H:i:s'),$id]);}
        if($action==='resolve') {if(($_POST['checked_account'] ?? '')!=='yes')throw new InvalidArgumentException('Confirm that you checked the real account.');query("UPDATE social_jobs SET status='sent',phase='manual_verified',error_message='Publication manually verified by administrator' WHERE id=? AND status='needs_review'",[$id]);}
        audit('Social job '.$action.' #'.$id);flash('Publishing job updated.');
    } else throw new InvalidArgumentException('Unknown social action.');
}
