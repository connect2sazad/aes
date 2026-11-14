<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit('CLI only');
$root=dirname(__DIR__); $c=require $root.'/config.php';
$db=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec(file_get_contents(__DIR__.'/schema.sql'));
require __DIR__.'/demo-schema.php';
$defaults=[
 'hero_title'=>'Power your future. Build with confidence.',
 'hero_text'=>'Rooftop solar and electrical distribution infrastructure, delivered across Odisha by Aanshu Engineering Solution Private Limited.',
 'about'=>'From a rooftop ready for solar to a new electrical distribution line, every project begins with understanding your site. Our work covers solar rooftop supply and associated services, overhead and underground electrical lines, and distribution transformer installation.',
 'solar_title'=>'Make your roof work for you.',
 'solar_text'=>'Solar rooftop supply with associated services for homes and businesses across Odisha. Speak with our team about site requirements, installation and the next steps for your project.',
 'grid_title'=>'Infrastructure that connects communities.',
 'grid_text'=>'Construction of new 11 kV / 33 kV overhead and underground electrical lines, and installation of new distribution transformers.',
 'scheme_text'=>'We work on rooftop solar projects under PM Surya Ghar: Muft Bijli Yojana across Odisha. Eligibility, vendor registration and subsidy approval must be checked through the official national portal and your DISCOM. Subsidy is not guaranteed.',
 'phone'=>'919114959111','email'=>$c['contact_email'] ?? '',
 'address'=>'Atri, Baghamari, Khordha – 752061, Odisha',
 'gstin'=>'21ABFCA4099J1ZL','cin'=>'U35105OD2026PTC053277',
];
$q=$db->prepare('INSERT IGNORE INTO content (content_key,content_value) VALUES (?,?)');
foreach($defaults as $k=>$v) $q->execute([$k,$v]);
echo "Schema installed; existing content preserved.\n";
