<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$c = content();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    rateLimit('enquiry', 5, 3600);
    try {
        if (!empty($_POST['website'])) throw new InvalidArgumentException('Unable to submit this request.');
        $name = field('name', 100);
        $phone = field('phone', 30);
        $email = field('email', 190, false);
        $service = field('service', 40);
        $message = field('message', 3000);
        if (!preg_match('/^[+0-9 ()-]{7,30}$/D', $phone)) throw new InvalidArgumentException('Enter a valid contact number.');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
        if (!in_array($service, ['Rooftop solar', 'Electrical distribution', 'Transformer installation', 'Other'], true)) throw new InvalidArgumentException('Choose a listed service.');
        if (($_POST['consent'] ?? '') !== 'yes') throw new InvalidArgumentException('Please consent to being contacted about your enquiry.');
        query('INSERT INTO enquiries (name,phone,email,service,message) VALUES (?,?,?,?,?)', [$name, $phone, $email, $service, $message]);
        $id = (int)db()->lastInsertId();
        // Fixed destination and headers; never use visitor input as a mail header.
        if ($config['notify_email'] && filter_var($config['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $sent = @mail($config['contact_email'], 'New website enquiry #' . $id, "Name: $name\nPhone: $phone\nEmail: $email\nService: $service\n\n$message");
            query('UPDATE enquiries SET notification_status=? WHERE id=?', [$sent ? 'accepted_by_mail_server' : 'failed', $id]);
        }
        flash('Thank you. Your enquiry has been saved. Our team will contact you using the details you provided.');
        redirect('/#contact');
    } catch (InvalidArgumentException $ex) {
        $error = $ex->getMessage();
    }
}
$projects = query('SELECT * FROM projects WHERE published=1 ORDER BY id DESC LIMIT 24')->fetchAll();
$reviews = query('SELECT * FROM reviews WHERE published=1 ORDER BY id DESC LIMIT 12')->fetchAll();
$posts = query('SELECT * FROM posts WHERE published=1 ORDER BY id DESC LIMIT 12')->fetchAll();
$wa = 'https://wa.me/' . $c['phone'] . '?text=' . rawurlencode('Hello Aanshu Engineering, I would like to discuss a solar / electrical project.');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1"><?php renderSeo(); ?>
    <link rel="icon" href="<?=e(appUrl())?>/assets/logo.jpg">
    <link rel="stylesheet" href="<?=e(appUrl())?>/assets/style.css">
    <script defer src="<?=e(appUrl())?>/assets/site.js"></script>
</head>

<body>
    <div class="topbar">
        <div class="wrap"><span>Solar energy. Stronger connections.</span><a href="tel:+<?= e($c['phone']) ?>">Call +<?= e($c['phone']) ?> ↗</a></div>
    </div>
    <header class="header">
        <div class="wrap nav"><a class="brand" href="<?=e(appUrl())?>/" aria-label="Aanshu Engineering home"><img src="<?=e(appUrl())?>/assets/logo.jpg" width="100" height="60" alt="AES"><span>AANSHU <b>ENGINEERING</b><small>SOLUTION PRIVATE LIMITED</small></span></a><button class="menu" aria-expanded="false" aria-controls="navigation">Menu ☰</button>
            <nav id="navigation"><a href="#services">Our expertise</a><a href="#about">About AES</a><a href="#projects">Our work</a><a href="#updates">Updates</a><a class="button small" href="#contact">Let’s talk <span>↗</span></a></nav>
        </div>
    </header>
    <main>
        <section class="hero">
            <div class="wrap hero-grid">
                <div class="hero-copy">
                    <div class="eyebrow"><span class="dot"></span> ENGINEERING A BRIGHTER ODISHA</div>
                    <h1><?= e($c['hero_title']) ?></h1>
                    <p><?= e($c['hero_text']) ?></p>
                    <div class="actions"><a class="button" href="#contact">Discuss your project ↗</a><a class="text-link" href="#services">Explore our services ↓</a></div>
                    <div class="hero-note"><span class="sun-symbol">☀</span>
                        <div>Local understanding. Lasting infrastructure.<small>Serving homes, businesses & distribution projects.</small></div>
                    </div>
                </div>
                <div class="hero-art">
                    <div class="art-label">THE NEXT CHAPTER IS <b>SOLAR.</b></div><img src="<?=e(appUrl())?>/assets/solar.svg" alt="Illustration of solar panels capturing sunlight, with electrical transmission infrastructure" width="720" height="650">
                    <div class="art-bottom"><span>01 / CLEAN ENERGY</span><span>ODISHA, INDIA ↗</span></div>
                </div>
            </div>
        </section>
        <div class="expertise-strip">
            <div class="wrap"><span>ROOFTOP SOLAR</span><i>✳</i><span>11 kV / 33 kV LINES</span><i>✳</i><span>DISTRIBUTION TRANSFORMERS</span></div>
        </div>
        <section id="services" class="section wrap">
            <div class="section-heading">
                <div>
                    <div class="eyebrow">01 — OUR EXPERTISE</div>
                    <h2>From sunlight to supply.<br>We make the connection.</h2>
                </div>
                <p>Two connected disciplines.<br>One team to discuss your requirements.</p>
            </div>
            <div class="services">
                <article class="service solar-service">
                    <div class="service-top"><span class="service-icon">☀</span><span>01 / RENEWABLE ENERGY</span></div>
                    <h3><?= e($c['solar_title']) ?></h3>
                    <p><?= e($c['solar_text']) ?></p>
                    <ul>
                        <li>Solar rooftop supply</li>
                        <li>Installation & associated services</li>
                        <li>Residential & business enquiries</li>
                    </ul><a href="#contact">Talk rooftop solar <span>↗</span></a>
                </article>
                <article class="service grid-service">
                    <div class="service-top"><span class="service-icon">ϟ</span><span>02 / ELECTRICAL INFRASTRUCTURE</span></div>
                    <h3><?= e($c['grid_title']) ?></h3>
                    <p><?= e($c['grid_text']) ?></p>
                    <ul>
                        <li>11 kV / 33 kV overhead lines</li>
                        <li>11 kV / 33 kV underground lines</li>
                        <li>Distribution transformer installation</li>
                    </ul><a href="#contact">Discuss electrical works <span>↗</span></a>
                </article>
            </div>
        </section>
        <section class="scheme">
            <div class="wrap scheme-grid">
                <div class="scheme-icon">☀<small>ROOFTOP<br>SOLAR</small></div>
                <div>
                    <div class="eyebrow">PM SURYA GHAR: MUFT BIJLI YOJANA</div>
                    <h2>A brighter rooftop.<br>A more informed start.</h2>
                    <p><?= e($c['scheme_text']) ?></p><a class="text-link" href="https://pmsuryaghar.gov.in/" target="_blank" rel="noopener noreferrer">Visit the official national portal ↗</a>
                </div>
            </div>
        </section>
        <section id="about" class="section wrap about-grid">
            <div>
                <div class="eyebrow">02 — MEET AANSHU ENGINEERING</div>
                <h2>Rooted in Odisha.<br>Built for what’s next.</h2>
            </div>
            <div>
                <p class="large-copy"><?= e($c['about']) ?></p>
                <div class="about-details">
                    <div><small>BASED IN</small><b>Khordha, Odisha</b></div>
                    <div><small>WORKING ACROSS</small><b>Odisha</b></div>
                </div><a class="text-link" href="#contact">Let’s understand your site ↗</a>
            </div>
        </section>
        <section id="projects" class="section work-section">
            <div class="wrap">
                <div class="section-heading">
                    <div>
                        <div class="eyebrow">03 — PROJECT GALLERY</div>
                        <h2>Good work deserves<br>to be seen.</h2>
                    </div>
                    <div class="filters" aria-label="Filter projects"><button class="active" data-filter="all" aria-pressed="true">All work</button><button data-filter="Solar" aria-pressed="false">Solar</button><button data-filter="Electrical" aria-pressed="false">Electrical</button></div>
                </div>
                <div class="project-grid"><?php foreach ($projects as $p): ?><article class="project" data-category="<?= e($p['category']) ?>"><a href="<?=e(appUrl())?>/project.php?id=<?= e($p['id']) ?>"><img loading="lazy" src="<?=e(appUrl())?>/media.php?file=<?= e($p['photo']) ?>" alt="<?= e($p['title']) ?>"></a><small><?= e($p['category']) ?> / <?= e($p['location']) ?></small>
                            <h3><?= e($p['title']) ?></h3>
                            <p><?= e($p['description']) ?></p><a class="text-link" href="<?=e(appUrl())?>/project.php?id=<?= e($p['id']) ?>">View project photos ↗</a>
                        </article><?php endforeach ?></div><?php if (!$projects): ?><div class="empty-work"><span>↗</span>
                        <div>
                            <h3>Your project could be next.</h3>
                            <p>Installation photographs will appear here as our team adds project stories.</p>
                        </div><a class="text-link" href="#contact">Start a conversation ↗</a>
                    </div><?php endif ?><p id="filter-empty" hidden>No projects in this category yet.</p>
            </div>
        </section>
        <section class="section wrap">
            <div class="section-heading">
                <div>
                    <div class="eyebrow">04 — THE WAY WE WORK</div>
                    <h2>A clear path from<br>first call to next steps.</h2>
                </div>
            </div>
            <div class="steps">
                <article><span>01</span>
                    <h3>Tell us what you need</h3>
                    <p>Share your location, electricity needs or electrical works requirements.</p>
                </article>
                <article><span>02</span>
                    <h3>Understand the site</h3>
                    <p>Discuss site conditions, scope and the information needed for a proposal.</p>
                </article>
                <article><span>03</span>
                    <h3>Agree on the plan</h3>
                    <p>Review the proposed scope, pricing, approvals and timeline before work begins.</p>
                </article>
            </div>
        </section>
        <?php if ($reviews): ?><section class="reviews section">
                <div class="wrap">
                    <div class="eyebrow">CUSTOMER EXPERIENCES</div>
                    <h2>In our customers’ words.</h2>
                    <div class="review-grid"><?php foreach ($reviews as $r): ?><blockquote>
                                <div class="stars" aria-label="<?= e($r['rating']) ?> out of 5 stars"><?= str_repeat('★', (int)$r['rating']) ?></div>
                                <p>“<?= e($r['quote']) ?>”</p>
                                <footer><b><?= e($r['customer']) ?></b><small><?= e($r['location']) ?></small></footer>
                            </blockquote><?php endforeach ?></div>
                </div>
            </section><?php endif ?>
        <section id="updates" class="section wrap">
            <div class="section-heading">
                <div>
                    <div class="eyebrow">05 — FROM THE TEAM</div>
                    <h2>Latest at AES.</h2>
                </div>
                <p>Project notes, company news<br>and useful updates.</p>
            </div>
            <div class="project-grid"><?php foreach ($posts as $post): ?><article class="project"><?php if ($post['photo']): ?><img loading="lazy" src="<?=e(appUrl())?>/media.php?file=<?= e($post['photo']) ?>" alt="<?= e($post['title']) ?>"><?php endif ?><small><?= e(date('d M Y', strtotime($post['created_at']))) ?></small>
                        <h3><?= e($post['title']) ?></h3>
                        <p class="preserve"><?= e($post['body']) ?></p>
                    </article><?php endforeach ?></div><?php if (!$posts): ?><p class="muted">Our first updates are on their way. For current project enquiries, speak with our team directly.</p><?php endif ?>
        </section>
        <section id="contact" class="contact section">
            <div class="wrap contact-grid">
                <div>
                    <div class="eyebrow">LET’S BUILD SOMETHING THAT LASTS</div>
                    <h2>Your next project<br>starts with a<br><em>conversation.</em></h2>
                    <p>Solar rooftop or electrical infrastructure?<br>Tell us what you have in mind.</p><a class="contact-number" href="tel:+<?= e($c['phone']) ?>">+<?= e($c['phone']) ?> ↗</a>
                    <div class="actions"><a class="button whatsapp" href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer">Chat on WhatsApp ↗</a><?php if ($c['email']): ?><a class="text-link" href="mailto:<?= e($c['email']) ?>">Send an email ↗</a><?php endif ?></div>
                    <p class="address"><?= e($c['address']) ?></p>
                </div>
                <form method="post" action="<?=e(appUrl())?>/#contact" class="enquiry-form"><?= csrf() ?><h3>Request a callback</h3>
                    <p>Share a few details. We’ll take it from here.</p><?php notice();
                                                                        if ($error): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif ?><div class="form-row"><label>Your name<input name="name" required maxlength="100" autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>"></label><label>Phone number<input type="tel" name="phone" required maxlength="30" autocomplete="tel" value="<?= e($_POST['phone'] ?? '') ?>"></label></div><label>Email <span class="muted">(optional)</span><input name="email" type="email" maxlength="190" autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label><label>I’m interested in<select name="service" required>
                            <option value="">Select a service</option><?php foreach (['Rooftop solar', 'Electrical distribution', 'Transformer installation', 'Other'] as $s): ?><option <?= ($_POST['service'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach ?>
                        </select></label><label>Tell us about your project<textarea name="message" rows="3" required maxlength="3000" placeholder="Location, requirements, or questions…"><?= e($_POST['message'] ?? '') ?></textarea></label>
                    <div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><label class="check"><input type="checkbox" name="consent" value="yes" required> <span>I agree to be contacted about this enquiry. <a href="<?=e(appUrl())?>/privacy.php">Privacy notice</a></span></label><button class="button" type="submit">Send enquiry <span>↗</span></button><small>Your details are used to respond to your enquiry.</small>
                </form>
            </div>
        </section>
    </main>
    <footer class="footer">
        <div class="wrap">
            <div class="footer-top"><a class="brand" href="<?=e(appUrl())?>/"><img src="<?=e(appUrl())?>/assets/logo.jpg" width="90" height="54" alt="AES"><span>AANSHU <b>ENGINEERING</b><small>SOLUTION PRIVATE LIMITED</small></span></a>
                <p>Solar energy.<br>Electrical expertise.<br>Across Odisha.</p>
                <div><a href="#services">Our expertise</a><a href="#projects">Project gallery</a><a href="#contact">Get in touch</a><a href="<?=e(appUrl())?>/privacy.php">Privacy</a></div>
            </div>
            <?php renderSocialLinks(); ?><div class="legal"><span>© <?= date('Y') ?> Aanshu Engineering Solution Private Limited</span><span>GSTIN: <?= e($c['gstin']) ?><br>CIN: <?= e($c['cin']) ?></span></div>
        </div>
    </footer><a class="floating-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer" aria-label="Contact us on WhatsApp">WhatsApp ↗</a>
</body>

</html>