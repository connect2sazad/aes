<?php
require dirname(__DIR__).'/app/bootstrap.php';
$s=seoSettings();$base=siteUrl($s);header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
if($base && $s['indexing']==='1') {
    $urls=[$base.'/',$base.'/privacy.php'];
    foreach(query('SELECT id FROM projects WHERE published=1 ORDER BY id')->fetchAll() as $p) $urls[]=$base.'/project.php?id='.(int)$p['id'];
    foreach($urls as $url) echo '<url><loc>'.htmlspecialchars($url,ENT_XML1 | ENT_QUOTES,'UTF-8').'</loc></url>';
}
echo '</urlset>';
