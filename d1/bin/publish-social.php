<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/publisher.php';
echo 'Processed '.runSocialWorker().' social job(s). Check Admin > Social accounts for results.'.PHP_EOL;
