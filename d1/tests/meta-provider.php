<?php
header('Content-Type: application/json');$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$method=$_SERVER['REQUEST_METHOD'];
$token=str_replace('Bearer ','',$_SERVER['HTTP_AUTHORIZATION'] ?? '');
file_put_contents(dirname(__DIR__).'/storage/meta-requests.jsonl',json_encode(['method'=>$method,'path'=>$path,'fields'=>array_keys($_POST)]).PHP_EOL,FILE_APPEND);
if($token==='test_denied'){http_response_code(400);echo json_encode(['error'=>['code'=>190]]);return;}
if($token==='test_uncertain'){http_response_code(503);echo json_encode(['error'=>['code'=>2]]);return;}
if($method==='POST' && str_ends_with($path,'/feed')){echo json_encode(['id'=>'11111_22222']);return;}
if($method==='POST' && str_ends_with($path,'/photos')){echo json_encode(['id'=>'33333','post_id'=>'11111_33333']);return;}
if($method==='POST' && str_ends_with($path,'/media_publish')){echo json_encode(['id'=>'55555']);return;}
if($method==='POST' && str_ends_with($path,'/media')){echo json_encode(['id'=>'44444']);return;}
if($method==='GET' && str_ends_with($path,'/44444')){echo json_encode(['status_code'=>$token==='test_processing'?'IN_PROGRESS':'FINISHED']);return;}
http_response_code(501);echo json_encode(['error'=>['code'=>501]]);

