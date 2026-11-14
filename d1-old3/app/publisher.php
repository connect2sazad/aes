<?php
final class PublishingFailure extends RuntimeException {
    public function __construct(string $message,public bool $uncertain=false) {parent::__construct($message);}
}
function metaRequest(string $method,string $path,array $data,string $token): array {
    global $config;
    $version=$config['meta_api_version'] ?? 'v27.0';
    if(!preg_match('/^v[0-9]{1,3}\.0$/D',$version))throw new PublishingFailure('Invalid Meta API version in private config.');
    $base='https://graph.facebook.com';
    // Loopback override is only usable in the explicitly disposable non-production test database.
    if(isset($config['social_test_base'])) {
        if($config['production'] || !str_contains($config['dsn'],'dbname=aanshu_test') || !preg_match('~^http://127\.0\.0\.1:[0-9]+$~D',$config['social_test_base']))throw new PublishingFailure('Refusing unsafe API test configuration.');
        $base=$config['social_test_base'];
    }
    $url=$base.'/'.$version.'/'.$path;
    if($method==='GET')$url.='?'.http_build_query($data);
    $curl=curl_init($url);$response='';
    curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_WRITEFUNCTION=>function($curl,$chunk)use(&$response){if(strlen($response)+strlen($chunk)>1024*1024)return 0;$response.=$chunk;return strlen($chunk);}]);
    if($method==='POST')curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($data));
    $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    if($ok===false)throw new PublishingFailure('No reliable response from Meta. Check the account before retrying.', $method==='POST');
    $json=json_decode($response,true);
    if($status<200 || $status>=300 || !is_array($json) || isset($json['error'])) {
        $code=(int)($json['error']['code'] ?? 0);
        $hint=$code===190?'Token expired or invalid. Update the account token.':($status===429?'Rate limited. Wait before retrying.':'Check token permissions, account ownership, image access and the configured Meta app.');
        throw new PublishingFailure('Meta request failed (HTTP '.$status.', code '.$code.'). '.$hint,$method==='POST' && ($status>=500 || $status<200 || $status<300));
    }
    return $json;
}
function metaId(array $result,string $key='id'): string {
    $id=(string)($result[$key] ?? '');
    if(!preg_match('/^[0-9_]{1,100}$/D',$id))throw new PublishingFailure('Meta returned no reliable publication ID. Check the account before retrying.',true);
    return $id;
}
function processSocialJob(array $job): void {
    $account=query('SELECT * FROM social_accounts WHERE channel=? AND enabled=1',[$job['channel']])->fetch();
    $post=query('SELECT published FROM posts WHERE id=?',[$job['post_id']])->fetch();
    if(!$account || $account['account_id']!==$job['account_id'] || !$post || !$post['published']) {
        query("UPDATE social_jobs SET status='cancelled',error_message='Account disabled/changed or website post unpublished' WHERE id=?",[$job['id']]);return;
    }
    try {
        if(!preg_match('/^[0-9]{5,80}$/D',$account['account_id']))throw new PublishingFailure('Account ID must be configured again.');
        $token=decryptSocialToken($account['token_cipher']);$id=$account['account_id'];
        $photoUrl=socialMediaUrl($job['photo']);
        if($job['photo'] && !is_file(ROOT.'/storage/photos/'.$job['photo']))throw new PublishingFailure('The queued image is missing. Restore the photo before retrying.');
        if($job['channel']==='facebook') {
            query("UPDATE social_jobs SET phase='publish' WHERE id=?",[$job['id']]);
            $result=metaRequest('POST',$id.($photoUrl?'/photos':'/feed'),$photoUrl?['url'=>$photoUrl,'caption'=>$job['caption'],'published'=>'true']:['message'=>$job['caption']],$token);
            $remote=metaId($result,isset($result['post_id'])?'post_id':'id');
        } else {
            if(!$photoUrl)throw new PublishingFailure('Instagram requires a public photo.');
            $container=$job['container_id'];
            if(!$container) {
                query("UPDATE social_jobs SET phase='create_container' WHERE id=?",[$job['id']]);
                $container=metaId(metaRequest('POST',$id.'/media',['image_url'=>$photoUrl,'caption'=>$job['caption']],$token));
                query("UPDATE social_jobs SET container_id=?,phase='container_ready' WHERE id=?",[$container,$job['id']]);
            }
            if(!preg_match('/^[0-9]{1,100}$/D',$container))throw new PublishingFailure('Invalid Instagram container ID.');
            $state=metaRequest('GET',$container,['fields'=>'status_code'],$token)['status_code'] ?? '';
            if($state==='IN_PROGRESS') {query("UPDATE social_jobs SET status='pending',available_at=?,error_message='Instagram is still processing the image' WHERE id=?",[date('Y-m-d H:i:s',time()+60),$job['id']]);return;}
            if($state==='PUBLISHED')throw new PublishingFailure('Instagram reports this container was already published. Verify and mark it as published.',true);
            if($state!=='FINISHED')throw new PublishingFailure('Instagram container is not publishable (processing error or expired). Create a new website post after fixing the image.');
            query("UPDATE social_jobs SET phase='publish' WHERE id=?",[$job['id']]);
            $remote=metaId(metaRequest('POST',$id.'/media_publish',['creation_id'=>$container],$token));
        }
        query("UPDATE social_jobs SET status='sent',phase='complete',remote_id=?,error_message=NULL WHERE id=?",[$remote,$job['id']]);
    } catch(PublishingFailure $e) {
        query('UPDATE social_jobs SET status=?,error_message=? WHERE id=?',[$e->uncertain?'needs_review':'failed',$e->getMessage(),$job['id']]);
    } catch(InvalidArgumentException $e) {
        query("UPDATE social_jobs SET status='failed',error_message=? WHERE id=?",[$e->getMessage(),$job['id']]);
    } catch(Throwable $e) {
        // Do not log raw HTTP responses or exception context that could contain a token.
        query("UPDATE social_jobs SET status='needs_review',error_message='Worker interrupted. Verify the account before retrying.' WHERE id=?",[$job['id']]);
    }
}
function runSocialWorker(int $limit=5): int {
    global $config;$lock='aes_social_'.substr(hash('sha256',$config['dsn']),0,32);
    if((int)query('SELECT GET_LOCK(?,0)',[$lock])->fetchColumn()!==1)return 0;
    $processed=0;
    try {
        query("UPDATE social_jobs SET status='needs_review',error_message='A previous worker stopped unexpectedly. Check the real account before retrying.' WHERE status='processing' AND updated_at < ?",[date('Y-m-d H:i:s',time()-600)]);
        $limit=max(1,min(20,$limit));
        $jobs=query("SELECT * FROM social_jobs WHERE status='pending' AND available_at<=? ORDER BY id LIMIT $limit",[date('Y-m-d H:i:s')])->fetchAll();
        foreach($jobs as $job) {query("UPDATE social_jobs SET status='processing' WHERE id=? AND status='pending'",[$job['id']]);processSocialJob($job);$processed++;}
    } finally {query('SELECT RELEASE_LOCK(?)',[$lock]);}
    return $processed;
}
