<?php
require dirname(__DIR__).'/app/bootstrap.php';
$s=seoSettings();$base=siteUrl($s);header('Content-Type: text/plain; charset=utf-8');
// Let crawlers fetch HTML to read noindex. robots.txt is not an access control.
echo "User-agent: *\nAllow: /\n";
if($base && $s['indexing']==='1') echo 'Sitemap: '.$base."/sitemap.php\n";
