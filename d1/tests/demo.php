<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$root=dirname(__DIR__);$c=require $root.'/config.php';
if($c['production'] || !preg_match('/(?:^|;)dbname=aanshu_test(?:;|$)/',$c['dsn']))exit("Requires disposable aanshu_test database with production=false.\n");
require $root.'/app/bootstrap.php';
$checks=0;$failed=false;$controlIds=[];
function demoCheck(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS: $label\n";}
function runDemo(string $mode): string {global $root;$pipes=[];$process=proc_open([PHP_BINARY,$root.'/bin/demo.php',$mode],[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);if(!is_resource($process))throw new RuntimeException('Cannot start demo command');$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException('Demo command failed: '.$err);return $out;}
try {
    runDemo('purge');
    $originalContent=query('SELECT content_key,content_value FROM content')->fetchAll(PDO::FETCH_KEY_PAIR);
    query("INSERT INTO posts (title,body,published) VALUES ('TEST CONTROL real record','Preserve this untagged control.',0)");$control=(int)db()->lastInsertId();$controlIds[]=$control;
    runDemo('seed');
    foreach(['projects'=>3,'posts'=>5,'reviews'=>5,'enquiries'=>8] as $table=>$count)demoCheck((int)query("SELECT COUNT(*) FROM $table WHERE demo_key IS NOT NULL")->fetchColumn()===$count,'Seed creates expected demo '.$table);
    demoCheck($originalContent===query('SELECT content_key,content_value FROM content')->fetchAll(PDO::FETCH_KEY_PAIR),'Seed leaves original company and contact content unchanged');
    $before=query('SELECT demo_key,id FROM projects WHERE demo_key IS NOT NULL ORDER BY id')->fetchAll();$beforeFiles=query('SELECT photo FROM demo_assets ORDER BY photo')->fetchAll(PDO::FETCH_COLUMN);
    runDemo('seed');
    demoCheck($before===query('SELECT demo_key,id FROM projects WHERE demo_key IS NOT NULL ORDER BY id')->fetchAll(),'Repeated seed preserves project IDs');
    demoCheck($beforeFiles===query('SELECT photo FROM demo_assets ORDER BY photo')->fetchAll(PDO::FETCH_COLUMN) && count($beforeFiles)===9,'Repeated seed does not duplicate the nine gallery images');
    $photos=query('SELECT pp.* FROM project_photos pp JOIN projects p ON p.id=pp.project_id WHERE p.demo_key IS NOT NULL')->fetchAll();demoCheck(count($photos)===9,'Every demo project has three gallery photos');
    foreach($beforeFiles as $photo){$info=getimagesize($root.'/storage/photos/'.$photo);demoCheck($info && $info[0]===1200 && $info[1]===800 && $info[2]===IMAGETYPE_JPEG,'Generated demo illustration is a valid JPEG');}
    demoCheck((int)query("SELECT COUNT(*) FROM reviews WHERE demo_key IS NOT NULL AND customer LIKE '[DEMO]%' AND quote LIKE '[DEMO%'")->fetchColumn()===5,'Every fictional review is visibly labelled');
    demoCheck((int)query("SELECT COUNT(*) FROM enquiries WHERE demo_key IS NOT NULL AND phone='0000000000' AND email LIKE '%@example.invalid' AND notification_status='demo_not_sent'")->fetchColumn()===8,'Enquiries use reserved fictional contacts and no notification state');
    $post=query('SELECT id FROM posts WHERE demo_key IS NOT NULL AND published=1 LIMIT 1')->fetchColumn();try{queueSocialPost((int)$post,'facebook');$blocked=false;}catch(InvalidArgumentException $e){$blocked=str_contains($e->getMessage(),'Demo posts');}demoCheck($blocked,'Demo posts cannot be queued to real social accounts');
    $homepage=file_get_contents('http://127.0.0.1:8086/');demoCheck(str_contains($homepage,'DEMONSTRATION CONTENT') && str_contains($homepage,'[DEMO]'),'Public page displays the demo notice and labelled records');
    demoCheck(str_contains($homepage,'Developed by') && str_contains($homepage,'https://connect2sazad.github.io/'),'Footer includes the requested developer credit and link');
    $photo=$beforeFiles[0];query('UPDATE posts SET photo=? WHERE id=?',[$photo,$control]);
    runDemo('purge');
    foreach(['projects','posts','reviews','enquiries'] as $table)demoCheck((int)query("SELECT COUNT(*) FROM $table WHERE demo_key IS NOT NULL")->fetchColumn()===0,'Purge removes tagged demo '.$table);
    demoCheck((bool)query('SELECT id FROM posts WHERE id=?',[$control])->fetch(),'Purge preserves untagged real records');
    demoCheck(is_file($root.'/storage/photos/'.$photo),'Purge preserves a demo image still referenced by a real record');
    query('UPDATE posts SET photo=NULL WHERE id=?',[$control]);runDemo('purge');demoCheck(!is_file($root.'/storage/photos/'.$photo) && (int)query('SELECT COUNT(*) FROM demo_assets')->fetchColumn()===0,'A later purge removes unused demo images without touching other files');
    demoCheck(!str_contains(file_get_contents('http://127.0.0.1:8086/'),'DEMONSTRATION CONTENT'),'Demo notice disappears after demo records are removed');
    echo "\n$checks demo/credit checks passed on real local MariaDB and HTTP.\n";
}catch(Throwable $e){$failed=true;echo 'FAIL: '.$e->getMessage().PHP_EOL;}
finally{foreach($controlIds as $id)query('DELETE FROM posts WHERE id=?',[$id]);}
exit($failed?1:0);
