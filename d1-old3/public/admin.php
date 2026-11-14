<?php
require dirname(__DIR__).'/app/bootstrap.php';
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
$error=''; $tab=(string)($_GET['tab'] ?? 'dashboard');
$allowed=['dashboard','content','projects','reviews','posts','enquiries','seo','social','help','security'];
if(!in_array($tab,$allowed,true)) $tab='dashboard';
if($_SERVER['REQUEST_METHOD']==='POST') {
    checkCsrf(); $action=(string)($_POST['action'] ?? '');
    try {
        if($action==='login') {
            rateLimit('login',8,900);
            $email=strtolower(field('email',190)); $password=field('password',200);
            $admin=query('SELECT * FROM admins WHERE email=?',[$email])->fetch();
            $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $valid=password_verify($password,$admin['password_hash'] ?? $dummy);
            if(!$admin || !$valid) throw new InvalidArgumentException('Email or password is incorrect.');
            session_regenerate_id(true);
            $_SESSION['admin_id']=(int)$admin['id']; $_SESSION['login_at']=$_SESSION['last_activity']=time();
            $_SESSION['password_fingerprint']=hash('sha256',$admin['password_hash']);
            $_SESSION['csrf']=bin2hex(random_bytes(32)); audit('Signed in'); redirect('./admin.php');
        }
        requireAdmin();
        if($tab==='social' && in_array($action,['profiles','account','disconnect','queue','retry','cancel','resolve'],true)) {handleSocialAction($action);redirect('/admin.php?tab=social');}
        if($action==='seo' && $tab==='seo') {
            if(!tableExists('seo_settings'))throw new InvalidArgumentException('Database update required: php bin/upgrade.php');
            $s=seoSettings();$next=[];
            foreach(['title'=>160,'description'=>320,'privacy_title'=>160,'privacy_description'=>320,'canonical_url'=>500,'social_title'=>160,'social_description'=>320,'verification'=>200] as $key=>$max) $next[$key]=field($key,$max,in_array($key,['title','description','privacy_title','privacy_description'],true));
            if($next['canonical_url']!=='' && !validateSiteUrl($next['canonical_url'])) throw new InvalidArgumentException('Enter the full public website URL, including any subfolder, without a query or fragment. Production requires HTTPS.');
            if($next['verification']!=='' && !preg_match('/^[A-Za-z0-9_-]+$/D',$next['verification'])) throw new InvalidArgumentException('Paste only the Google verification token, not HTML.');
            $next['indexing']=isset($_POST['indexing'])?'1':'0';
            $photo=uploadPhoto();$next['social_photo']=$photo ?? (isset($_POST['remove_social_photo'])?'':$s['social_photo']);
            db()->beginTransaction();
            foreach($next as $key=>$value) query('INSERT INTO seo_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);
            audit('Updated SEO settings');db()->commit();flash('SEO settings saved. Search engines decide when changes appear.');redirect('./admin.php?tab=seo');
        }
        if($action==='logout') { audit('Signed out'); $_SESSION=[]; session_destroy(); redirect('./admin.php'); }
        if($action==='password') {
            $current=field('current_password',200); $new=field('new_password',200);
            $old=query('SELECT password_hash FROM admins WHERE id=?',[$_SESSION['admin_id']])->fetchColumn();
            if(!password_verify($current,$old)) throw new InvalidArgumentException('Current password is incorrect.');
            if(strlen($new)<14) throw new InvalidArgumentException('Use at least 14 characters for the new password.');
            $hash=password_hash($new,PASSWORD_ARGON2ID); query('UPDATE admins SET password_hash=? WHERE id=?',[$hash,$_SESSION['admin_id']]);
            session_regenerate_id(true); $_SESSION['password_fingerprint']=hash('sha256',$hash); audit('Changed password');
            flash('Password changed. Other sessions are invalidated.'); redirect('./admin.php?tab=security');
        }
        if($action==='content') {
            $existing=content(); $values=[];
            foreach($existing as $key=>$value) $values[$key]=field($key,in_array($key,['phone','gstin','cin'])?40:4000,!in_array($key,['email'],true));
            if(!preg_match('/^[1-9][0-9]{7,14}$/D',$values['phone'])) throw new InvalidArgumentException('Phone must include country code and digits only.');
            if($values['email']!=='' && !filter_var($values['email'],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Invalid contact email.');
            db()->beginTransaction();
            foreach($values as $key=>$value) query('UPDATE content SET content_value=? WHERE content_key=?',[$value,$key]);
            audit('Updated page content'); db()->commit(); flash('Website content updated.'); redirect('./admin.php?tab=content');
        }
        // Table and column names come only from this hardcoded allowlist; values are always bound.
        $entities=['projects'=>['title'=>160,'category'=>30,'location'=>160,'description'=>3000], 'reviews'=>['customer'=>100,'location'=>160,'quote'=>2000,'rating'=>1], 'posts'=>['title'=>160,'body'=>5000]];
        if(isset($entities[$tab]) && in_array($action,['save','delete'],true)) {
            $id=filter_var($_POST['id'] ?? 0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if($id===false) throw new InvalidArgumentException('Invalid record.');
            $old=$id?query("SELECT * FROM $tab WHERE id=?",[$id])->fetch():null;
            if($id && !$old) throw new InvalidArgumentException('Record no longer exists.');
            if($action==='delete') {
                query("DELETE FROM $tab WHERE id=?",[$id]); audit('Deleted '.$tab.' #'.$id);
                flash('Record deleted.'); redirect('./admin.php?tab='.$tab);
            }
            $values=[];
            foreach($entities[$tab] as $key=>$max) $values[$key]=field($key,$max);
            if($tab==='projects' && !in_array($values['category'],['Solar','Electrical'],true)) throw new InvalidArgumentException('Choose a valid category.');
            if($tab==='reviews' && !in_array($values['rating'],['1','2','3','4','5'],true)) throw new InvalidArgumentException('Choose a rating from 1 to 5.');
            if($tab==='reviews' && empty($_POST['permission'])) throw new InvalidArgumentException('Confirm you have permission to publish this review.');
            if($tab==='projects') {
                $values['published']=isset($_POST['published'])?1:0;saveProject($values,$id);
                flash('Project saved.');redirect('./admin.php?tab=projects');
            }
            if($tab!=='reviews') {
                $photo=uploadPhoto(); $values['photo']=$photo ?? ($old['photo'] ?? null);
                if($tab==='projects' && !$values['photo']) throw new InvalidArgumentException('A project photograph is required.');
            }
            $values['published']=isset($_POST['published'])?1:0;
            if($id) {
                $assign=implode(',',array_map(fn($k)=>"$k=?",array_keys($values)));
                query("UPDATE $tab SET $assign WHERE id=?",[...array_values($values),$id]);
            } else {
                $columns=implode(',',array_keys($values)); $marks=implode(',',array_fill(0,count($values),'?'));
                query("INSERT INTO $tab ($columns) VALUES ($marks)",array_values($values)); $id=(int)db()->lastInsertId();
            }
            audit('Saved '.$tab.' #'.$id); flash('Saved. '.($values['published']?'Visible on the website.':'Kept as a draft.')); redirect('./admin.php?tab='.$tab);
        }
        if($tab==='enquiries') {
            $id=filter_var($_POST['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if(!$id) throw new InvalidArgumentException('Invalid enquiry.');
            if($action==='delete') { query('DELETE FROM enquiries WHERE id=?',[$id]); audit('Deleted enquiry #'.$id); }
            elseif($action==='status') {
                $status=field('status',20);
                if(!in_array($status,['new','contacted','closed'],true)) throw new InvalidArgumentException('Invalid status.');
                query('UPDATE enquiries SET status=? WHERE id=?',[$status,$id]); audit('Updated enquiry #'.$id);
            } else throw new InvalidArgumentException('Invalid action.');
            flash('Enquiry updated.'); redirect('./admin.php?tab=enquiries');
        }
    } catch(InvalidArgumentException $ex) { if(db()->inTransaction()) db()->rollBack(); $error=$ex->getMessage(); }
}
$logged=authenticated();
$labels=['dashboard'=>'Overview','content'=>'Page content','projects'=>'Project gallery','reviews'=>'Customer reviews','posts'=>'Posts & social','enquiries'=>'Enquiries','seo'=>'SEO settings','social'=>'Social accounts','help'=>'Help & docs','security'=>'Security'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Admin | Aanshu Engineering</title><link rel="stylesheet" href="<?=e(appUrl())?>/assets/style.css"><link rel="stylesheet" href="<?=e(appUrl())?>/assets/admin.css"><script defer src="<?=e(appUrl())?>/assets/site.js"></script></head><body class="admin-body">
<?php if(!$logged): ?><main class="login"><a href="<?=e(appUrl())?>/"><img src="<?=e(appUrl())?>/assets/logo.jpg" width="130" alt="AES"></a><div class="eyebrow">AANSHU ENGINEERING</div><h1>Welcome back.</h1><p>Sign in to manage your website.</p><?php if($error): ?><div class="notice error" role="alert"><?=e($error)?></div><?php endif ?><form method="post"><?=csrf()?><input type="hidden" name="action" value="login"><label>Email<input name="email" type="email" autocomplete="username" required maxlength="190"></label><label>Password<input name="password" type="password" autocomplete="current-password" required maxlength="200"></label><button class="button" type="submit">Sign in ↗</button></form><small>Forgot your password? Ask the site administrator to reset it using the server CLI.</small><a class="text-link" href="<?=e(appUrl())?>/">← Back to website</a></main>
<?php else: ?><aside class="sidebar"><a class="admin-brand" href="<?=e(appUrl())?>/admin.php"><img src="<?=e(appUrl())?>/assets/logo.jpg" width="100" alt="AES"><b>Website manager</b></a><nav><?php foreach($labels as $key=>$label): ?><a class="<?=$tab===$key?'selected':''?>" href="<?=e(appUrl())?>/admin.php?tab=<?=e($key)?>"><?=e($label)?></a><?php endforeach ?></nav><a class="text-link" href="<?=e(appUrl())?>/" target="_blank" rel="noopener">View website ↗</a><form method="post"><?=csrf()?><button name="action" value="logout" class="logout">Sign out</button></form></aside><main class="admin-main"><div class="admin-heading"><div><div class="eyebrow">AES / ADMINISTRATION</div><h1><?=e($labels[$tab])?></h1></div><span class="admin-badge">Secure session</span></div><?php notice(); if($error): ?><div class="notice error" role="alert"><?=e($error)?></div><?php endif ?>
<?php if($tab==='dashboard'): ?><div class="stat-grid"><?php foreach(['projects'=>'Projects','reviews'=>'Reviews','posts'=>'Posts','enquiries'=>'Enquiries'] as $table=>$label): ?><a href="<?=e(appUrl())?>/admin.php?tab=<?=e($table)?>"><span><?=e($label)?></span><strong><?=e(query("SELECT COUNT(*) FROM $table")->fetchColumn())?></strong><small>Manage <?=e(strtolower($label))?> ↗</small></a><?php endforeach ?></div><div class="admin-card"><h2>Make this website yours.</h2><p>Update the page copy, upload real installation photos, and add customer reviews with permission. Drafts stay private until you tick “Publish on website”.</p><a class="button" href="<?=e(appUrl())?>/admin.php?tab=content">Edit page content ↗</a></div><div class="admin-card"><h2>Before sharing your website</h2><p>Confirm the phone number, add a business email, verify company identifiers and scheme wording, and replace the gallery with approved project photographs.</p><p>Configure public profile links and authorized publishing accounts under Social accounts. Read Help & docs for setup, photo uploads and troubleshooting.</p></div>
<?php elseif($tab==='social'): require __DIR__.'/../app/social-admin.php'; ?>
<?php elseif($tab==='help'): require __DIR__.'/../app/help-admin.php'; ?>
<?php elseif($tab==='seo'): require __DIR__.'/../app/seo-admin.php'; ?>
<?php elseif($tab==='content'): $c=content(); ?><div class="admin-card"><p>Plain text only. Your layout stays consistent and scripts or HTML cannot be inserted.</p><form method="post"><?=csrf()?><input type="hidden" name="action" value="content"><?php foreach($c as $key=>$value): ?><label><?=e(ucwords(str_replace('_',' ',$key)))?><?php if(in_array($key,['phone','email','gstin','cin'],true)): ?><input name="<?=e($key)?>" value="<?=e($value)?>" maxlength="<?=$key==='email'?190:40?>" <?=$key==='email'?'type="email"':'required'?>><?php else: ?><textarea name="<?=e($key)?>" rows="<?=strlen($value)>160?4:2?>" maxlength="4000" required><?=e($value)?></textarea><?php endif ?></label><?php endforeach ?><button class="button">Save website content</button></form></div>
<?php elseif(in_array($tab,['projects','reviews','posts'],true)):
 $editId=filter_var($_GET['edit'] ?? 0,FILTER_VALIDATE_INT); $edit=$editId?query("SELECT * FROM $tab WHERE id=?",[$editId])->fetch():[]; $edit=$edit ?: [];
 if($error && ($_POST['action'] ?? '')==='save') $edit=array_merge($edit,$_POST);
 $fields=match($tab) { 'projects'=>['title','category','location','description'], 'reviews'=>['customer','location','rating','quote'], 'posts'=>['title','body'] };
?>
<?php if($tab==='posts'): ?><div class="admin-card social-notice"><h2>Write once. Publish to your accounts.</h2><p>Save and publish your website post first. Then open Social accounts to queue its caption and photo to Facebook or Instagram. Each network has its own publishing status; editing the website later does not change an existing queued or sent snapshot.</p><a class="button" href="<?=e(appUrl('/admin.php?tab=social'))?>">Manage social publishing ↗</a></div><?php endif ?>
<div class="admin-card"><h2><?=$edit?'Edit':'Add'?> <?=match($tab){'projects'=>'project','reviews'=>'review','posts'=>'post'}?></h2><form method="post" enctype="multipart/form-data"><?=csrf()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e($edit['id'] ?? 0)?>"><?php foreach($fields as $key): ?><label><?=e(ucfirst($key))?><?php if($key==='category'): ?><select name="category"><option <?=($edit[$key] ?? '')==='Solar'?'selected':''?>>Solar</option><option <?=($edit[$key] ?? '')==='Electrical'?'selected':''?>>Electrical</option></select><?php elseif($key==='rating'): ?><select name="rating"><?php for($n=5;$n>=1;$n--): ?><option value="<?=$n?>" <?=($edit[$key] ?? 5)==$n?'selected':''?>><?=$n?> stars</option><?php endfor ?></select><?php elseif(in_array($key,['body','description','quote'],true)): ?><textarea name="<?=e($key)?>" rows="5" maxlength="<?=$key==='body'?5000:($key==='quote'?2000:3000)?>" required><?=e($edit[$key] ?? '')?></textarea><?php else: ?><input name="<?=e($key)?>" value="<?=e($edit[$key] ?? '')?>" maxlength="<?=$key==='customer'?100:160?>" required><?php endif ?></label><?php endforeach ?>
<?php if($tab!=='reviews'): ?><?php if($tab==='projects'): $photos=!empty($edit['id'])?projectPhotos((int)$edit['id']):[]; ?>
<div class="photo-manager"><?php foreach($photos as $photo): ?><div class="photo-tile"><img src="<?=e(appUrl())?>/media.php?file=<?=e($photo['photo'])?>" alt="<?=e($photo['alt_text'])?>"><label class="check"><input type="radio" name="cover_photo" value="<?=e($photo['id'])?>" <?=$photo['photo']===($edit['photo'] ?? '')?'checked':''?>><span>Cover photo</span></label><label>Alt text<input name="alt[<?=e($photo['id'])?>]" value="<?=e($photo['alt_text'])?>" maxlength="240"></label><label class="check"><input type="checkbox" name="remove_photos[]" value="<?=e($photo['id'])?>"><span>Remove when saved</span></label></div><?php endforeach ?></div>
<label>Add project photos<input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" <?=!$photos?'required':''?>><small>Choose several files together. Maximum 12 photos per project; 5 MB / 20 megapixels per image. Existing photos stay unless marked for removal. New photos use the project title as alt text; edit to describe each image after saving. Only upload approved images. Re-select files after a validation error.</small></label>
<?php else: ?><?php if(!empty($edit['photo'])): ?><img class="thumbnail" src="<?=e(appUrl())?>/media.php?file=<?=e($edit['photo'])?>" alt="Current photo"><?php endif ?><label>Photograph (optional)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, WebP. Maximum 5 MB / 20 megapixels.</small></label><?php endif ?><?php else: ?><label class="check"><input type="checkbox" name="permission" required><span>I have the customer’s permission to publish this genuine review.</span></label><?php endif ?><label class="check"><input type="checkbox" name="published" <?=!empty($edit['published'])?'checked':''?>><span>Publish on website</span></label><button class="button">Save <?=$tab==='posts'?'post':'record'?></button> <a class="text-link" href="<?=e(appUrl())?>/admin.php?tab=<?=e($tab)?>">Clear form</a></form></div>
<h2>Saved <?=e($tab)?></h2><?php $rows=query("SELECT * FROM $tab ORDER BY id DESC LIMIT 200")->fetchAll(); if(!$rows): ?><p>No records yet.</p><?php endif ?><?php foreach($rows as $row): ?><article class="admin-card record"><?php if(!empty($row['photo'])): ?><a href="<?=e(appUrl())?>/media.php?file=<?=e($row['photo'])?>" target="_blank" rel="noopener"><img class="thumbnail" src="<?=e(appUrl())?>/media.php?file=<?=e($row['photo'])?>" alt="<?=e($row['title'])?>"></a><?php endif ?><div><span class="status <?=$row['published']?'live':'draft'?>"><?=$row['published']?'On website':'Draft'?></span><h3><?=e($row['title'] ?? $row['customer'])?></h3><p id="caption-<?=e($row['id'])?>" class="preserve"><?=e($row['body'] ?? $row['description'] ?? $row['quote'])?></p><div class="record-actions"><a class="text-link" href="<?=e(appUrl())?>/admin.php?tab=<?=e($tab)?>&edit=<?=e($row['id'])?>">Edit ↗</a><?php if($tab==='posts'): ?><button class="secondary" data-copy="caption-<?=e($row['id'])?>">Copy caption</button><?php endif ?><form method="post" data-confirm="Delete this record permanently?"><?=csrf()?><input type="hidden" name="id" value="<?=e($row['id'])?>"><button class="danger" name="action" value="delete">Delete</button></form></div></div></article><?php endforeach ?>
<?php elseif($tab==='enquiries'): ?><p>Newest first. Delete details when no longer needed; review enquiries older than 180 days. Up to 200 recent enquiries shown.</p><?php $rows=query('SELECT * FROM enquiries ORDER BY id DESC LIMIT 200')->fetchAll(); if(!$rows): ?><div class="admin-card">No enquiries yet.</div><?php endif ?><?php foreach($rows as $r): ?><article class="admin-card"><span class="status"><?=e($r['status'])?></span><h2><?=e($r['name'])?></h2><p><?=e($r['service'])?> · <?=e($r['created_at'])?></p><a href="tel:<?=e(preg_replace('/[^+0-9]/','',$r['phone']))?>"><?=e($r['phone'])?></a><?php if($r['email']): ?> · <a href="mailto:<?=e($r['email'])?>"><?=e($r['email'])?></a><?php endif ?><p class="preserve"><?=e($r['message'])?></p><small>Email notification: <?=e(str_replace('_',' ',$r['notification_status']))?></small><div class="record-actions"><form method="post" class="inline-form"><?=csrf()?><input type="hidden" name="id" value="<?=e($r['id'])?>"><select name="status"><?php foreach(['new','contacted','closed'] as $s): ?><option <?=$s===$r['status']?'selected':''?>><?=e($s)?></option><?php endforeach ?></select><button class="secondary" name="action" value="status">Update</button></form><form method="post" data-confirm="Permanently delete this enquiry and its contact details?"><?=csrf()?><input type="hidden" name="id" value="<?=e($r['id'])?>"><button class="danger" name="action" value="delete">Delete enquiry</button></form></div></article><?php endforeach ?>
<?php elseif($tab==='security'): ?><div class="admin-card"><h2>Change password</h2><form method="post"><?=csrf()?><input type="hidden" name="action" value="password"><label>Current password<input type="password" name="current_password" autocomplete="current-password" maxlength="200" required></label><label>New password<input type="password" name="new_password" autocomplete="new-password" minlength="14" maxlength="200" required></label><button class="button">Change password</button></form><p>Sessions expire after 30 minutes without activity, with an absolute limit of 8 hours. Use a unique password stored in a password manager.</p></div><div class="admin-card"><h2>Recent activity</h2><?php foreach(query('SELECT action,created_at FROM audit_log ORDER BY id DESC LIMIT 30')->fetchAll() as $r): ?><p><small><?=e($r['created_at'])?></small> — <?=e($r['action'])?></p><?php endforeach ?></div><?php endif ?>
</main><?php endif ?></body></html>
