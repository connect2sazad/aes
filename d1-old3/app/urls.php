<?php
// Browser paths are independent of filesystem includes and the public canonical domain.
function appBasePath(): string {
    global $config;
    $path=$config['base_path'] ?? null;
    if($path===null) $path=PHP_SAPI==='cli'?'':str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $path=rtrim((string)$path,'/');
    if($path==='.' || $path==='/') return '';
    if($path!=='' && (!str_starts_with($path,'/') || str_starts_with($path,'//') || preg_match('~[\x00-\x20?#\\\\]|(?:^|/)\.\.(?:/|$)~',$path))) throw new RuntimeException('Invalid base_path configuration.');
    return $path;
}
function appUrl(string $path=''): string {
    if($path==='') return appBasePath();
    if(str_starts_with($path,'./')) $path=substr($path,2);
    if(preg_match('~^(?:https?:)?//|[\r\n]~i',$path)) throw new InvalidArgumentException('External redirects are not allowed.');
    return appBasePath().'/'.ltrim($path,'/');
}
