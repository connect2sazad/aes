<?php
// Container-only configuration: secrets are runtime mounts, never image layers.
$secret=static function(string $name): string {
    $path=getenv($name.'_FILE');
    if(!$path || !is_readable($path))throw new RuntimeException('Missing runtime secret: '.$name);
    $value=trim((string)file_get_contents($path));
    if($value==='')throw new RuntimeException('Empty runtime secret: '.$name);
    return $value;
};
return [
    'dsn'=>'mysql:host=db;port=3306;dbname=aanshu;charset=utf8mb4',
    'db_user'=>getenv('DB_USER') ?: 'aanshu_app','db_password'=>$secret('DB_PASSWORD'),
    'app_key'=>$secret('APP_KEY'),
    'production'=>filter_var(getenv('APP_PRODUCTION') ?: 'true',FILTER_VALIDATE_BOOLEAN),
    'base_url'=>getenv('APP_URL') ?: '', 'base_path'=>null,
    'contact_email'=>getenv('CONTACT_EMAIL') ?: '',
    // mail() is deliberately disabled: configure a tested mail transport before enabling it.
    'notify_email'=>false,'meta_api_version'=>getenv('META_API_VERSION') ?: 'v27.0',
];
