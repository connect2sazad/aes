<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit('CLI only');
$directory=__DIR__.'/secrets';
if(!is_dir($directory) && !mkdir($directory,0700,true))throw new RuntimeException('Cannot create private secret directory.');
if(PHP_OS_FAMILY!=='Windows')chmod($directory,0700);
foreach(['db_password','db_root_password','app_key'] as $name){
    $file=$directory.'/'.$name.'.txt';
    if(file_exists($file)){echo $name." already exists; preserved.\n";continue;}
    $handle=fopen($file,'x');if(!$handle)throw new RuntimeException('Cannot create secret file.');
    fwrite($handle,bin2hex(random_bytes(32)).PHP_EOL);fclose($handle);
    // Parent is owner-only (0700). Files must be readable by www-data inside Compose mounts.
    if(PHP_OS_FAMILY!=='Windows')chmod($file,0444);
    echo $name." generated (value not printed).\n";
}
echo "Keep docker/secrets private and backed up. Never regenerate secrets for an existing database volume.\n";
