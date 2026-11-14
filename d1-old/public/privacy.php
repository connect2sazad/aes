<?php require dirname(__DIR__) . '/app/bootstrap.php';
$c = content(); ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Privacy | Aanshu Engineering</title>
    <link rel="stylesheet" href="./assets/style.css">
</head>

<body>
    <main class="wrap section privacy"><a class="text-link" href="/">← Back to home</a>
        <h1>Privacy notice</h1>
        <p>Aanshu Engineering Solution Private Limited uses your name, contact details, service selection and message to respond to your enquiry and manage follow-up. Please do not submit financial information, identity documents or other sensitive details.</p>
        <h2>How information is handled</h2>
        <p>Enquiries are stored in our restricted administration system. If email notifications are enabled, a copy may also be sent to the company inbox. Our hosting and email providers may process this information to operate those services. We do not sell your enquiry details.</p>
        <h2>Cookies and external services</h2>
        <p>This website uses a session cookie for form security and administration. It does not use advertising or analytics cookies. WhatsApp and email links open external services governed by their own privacy policies.</p>
        <h2>Retention and requests</h2>
        <p>Unneeded enquiries are intended to be deleted within 180 days, except where records must be retained for an ongoing project or legal requirements. Contact the company to request access, correction or deletion.</p>
        <p><?= e($c['address']) ?><br><a href="tel:+<?= e($c['phone']) ?>">+<?= e($c['phone']) ?></a><?php if ($c['email']): ?><br><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?php endif ?></p>
        <p class="muted">Last updated: 14 November 2026.</p>
    </main>
</body>

</html>