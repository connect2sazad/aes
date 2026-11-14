<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$p = $id ? query('SELECT * FROM projects WHERE id=? AND published=1', [$id])->fetch() : false;
if (!$p) {
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    exit('Project not found.');
}
$photos = projectPhotos((int)$p['id']);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1"><?php renderSeo('project', $p); ?>
    <link rel="stylesheet" href="<?=e(appUrl())?>/assets/style.css">
</head>

<body>
    <main class="wrap section"><a class="text-link" href="<?=e(appUrl())?>/#projects">← All projects</a>
        <div class="section-heading project-heading">
            <div>
                <div class="eyebrow"><?= e($p['category']) ?> / <?= e($p['location']) ?></div>
                <h1><?= e($p['title']) ?></h1>
            </div>
        </div>
        <p class="large-copy preserve"><?= e($p['description']) ?></p>
        <div class="project-photo-grid"><?php foreach ($photos as $photo): ?><figure><a href="<?=e(appUrl())?>/media.php?file=<?= e($photo['photo']) ?>" target="_blank" rel="noopener"><img loading="lazy" src="<?=e(appUrl())?>/media.php?file=<?= e($photo['photo']) ?>" alt="<?= e($photo['alt_text'] ?: $p['title']) ?>"></a></figure><?php endforeach ?></div>
        <div class="actions"><a class="button" href="<?=e(appUrl())?>/#contact">Discuss a similar project ↗</a></div>
    </main>
</body>

</html>