<?php
require __DIR__.'/bootstrap.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$tabs=['dashboard'=>'Overview','content'=>'Page content','projects'=>'Project photos','reviews'=>'Customer reviews','posts'=>'Posts & social','enquiries'=>'Enquiries','account'=>'Password'];
$tab=$_GET['tab']??'dashboard'; if(!isset($tabs[$tab])) $tab='dashboard';
$error=''; $notice=$_SESSION['flash']??''; unset($_SESSION['flash']);
if($_SERVER['REQUEST_METHOD']==='POST') {
 checkCsrf();
 try {
  $action=$_POST['action']??'';
  if($action==='login') {
   rateLimit('login',8,900);
   $email=field('email',190); $pass=field('password',512);
   $q=db()->prepare('SELECT * FROM admins WHERE email=?'); $q->execute([$email]); $admin=$q->fetch();
   $hash=$admin ? $admin['password_hash'] : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
   if(!password_verify($pass,$hash) || !$admin) throw new RuntimeException('Invalid email or password.');
   session_regenerate_id(true); $_SESSION['admin']=$admin['id']; $_SESSION['active']=time(); $_SESSION['csrf']=bin2hex(random_bytes(32));
   header('Location: admin.php'); exit;
  }
  if(!isAdmin()) throw new RuntimeException('Your session expired. Please sign in again.');
  $_SESSION['active']=time();
  if($action==='logout') { $_SESSION=[]; session_destroy(); header('Location: admin.php'); exit; }
  if($action==='content') {
   $allowed=['home_title','home_intro','about_body','service_solar','service_line','service_transformer','service_underground'];
   $q=db()->prepare('INSERT INTO content(name,body) VALUES(?,?) ON DUPLICATE KEY UPDATE body=VALUES(body)');
   db()->beginTransaction(); foreach($allowed as $key) $q->execute([$key,field($key,6000)]); db()->commit();
  } elseif($action==='project') {
   $title=field('title',160); $description=field('description',4000);
   $file=$_FILES['image']??null;
   if(!$file || $file['error']!==UPLOAD_ERR_OK || $file['size']>5*1024*1024) throw new RuntimeException('Upload a JPG, PNG or WebP image under 5 MB.');
   $type=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$type]??null;
   $size=getimagesize($file['tmp_name']);
   if(!$ext || !$size || $size[0]>8000 || $size[1]>8000 || $size[0]*$size[1]>20000000) throw new RuntimeException('Choose a valid image up to 20 megapixels and 8000 pixels per side.');
   if(!function_exists('imagecreatefromstring')) throw new RuntimeException('The PHP GD extension must be enabled for secure image processing.');
   $image=imagecreatefromstring(file_get_contents($file['tmp_name']));
   if(!$image) throw new RuntimeException('This image could not be processed.');
   $name=bin2hex(random_bytes(20)).'.jpg';
   if(!imagejpeg($image,__DIR__.'/uploads/'.$name,86)) throw new RuntimeException('Unable to save the image.');
   imagedestroy($image);
   try { $q=db()->prepare('INSERT INTO projects(title,description,image) VALUES(?,?,?)'); $q->execute([$title,$description,$name]); }
   catch(Throwable $ex) { unlink(__DIR__.'/uploads/'.$name); throw $ex; }
  } elseif($action==='review') {
   if(empty($_POST['consent'])) throw new RuntimeException('Confirm permission to publish the customer’s feedback.');
   $q=db()->prepare('INSERT INTO reviews(customer,body) VALUES(?,?)'); $q->execute([field('customer',100),field('body',4000)]);
  } elseif($action==='post') {
   $q=db()->prepare('INSERT INTO posts(title,body) VALUES(?,?)'); $q->execute([field('title',160),field('body',6000)]);
  } elseif($action==='edit') {
   $table=field('table',20); $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
   if(!$id || !in_array($table,['projects','reviews','posts'],true)) throw new RuntimeException('Invalid record.');
   if($table==='projects') { $q=db()->prepare('UPDATE projects SET title=?,description=? WHERE id=?'); $q->execute([field('title',160),field('description',4000),$id]); }
   elseif($table==='reviews') { $q=db()->prepare('UPDATE reviews SET customer=?,body=? WHERE id=?'); $q->execute([field('customer',100),field('body',4000),$id]); }
   else { $q=db()->prepare('UPDATE posts SET title=?,body=? WHERE id=?'); $q->execute([field('title',160),field('body',6000),$id]); }
  } elseif($action==='delete') {
   $table=field('table',20);
   if(!in_array($table,['projects','reviews','posts','enquiries'],true)) throw new RuntimeException('Invalid record.');
   $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT); if(!$id) throw new RuntimeException('Invalid record.');
   $image=null;
   if($table==='projects') { $q=db()->prepare('SELECT image FROM projects WHERE id=?'); $q->execute([$id]); $image=$q->fetchColumn(); }
   $q=db()->prepare("DELETE FROM $table WHERE id=?"); $q->execute([$id]);
   if($image && preg_match('/^[a-f0-9]{40}\.jpg$/',$image) && is_file(__DIR__.'/uploads/'.$image)) unlink(__DIR__.'/uploads/'.$image);
  } elseif($action==='password') {
   $old=field('current_password',512); $new=field('new_password',512);
   $q=db()->prepare('SELECT password_hash FROM admins WHERE id=?'); $q->execute([$_SESSION['admin']]);
   if(!password_verify($old,$q->fetchColumn())) throw new RuntimeException('Current password is incorrect.');
   if(strlen($new)<14 || strlen($new)>72) throw new RuntimeException('Use 14 to 72 bytes for the password.');
   $q=db()->prepare('UPDATE admins SET password_hash=? WHERE id=?'); $q->execute([password_hash($new,PASSWORD_DEFAULT),$_SESSION['admin']]); session_regenerate_id(true);
  } else { throw new RuntimeException('Unknown action.'); }
  $_SESSION['flash']='Changes saved.'; header('Location: admin.php?tab='.rawurlencode($tab)); exit;
 } catch(Throwable $ex) {
  try { if(db()->inTransaction()) db()->rollBack(); } catch(Throwable $ignored) {}
  $error=($ex instanceof PDOException || $ex->getMessage()==='Database not configured') ? 'Database unavailable. Check the server configuration.' : $ex->getMessage();
 }
}
$logged=isAdmin(); if($logged) $_SESSION['active']=time();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin · Aanshu Engineering Solution</title><link rel="stylesheet" href="assets/style.css"><script src="assets/admin.js" defer></script></head><body class="admin-body"><div class="admin-header"><div class="wrap"><strong>AES / Website administration</strong><a href="index.php">View website ↗</a></div></div>
<?php if(!$logged): ?><main class="login"><div class="eyebrow">AUTHORIZED ACCESS</div><h1>Welcome back.</h1><p>Sign in to manage the Aanshu website.</p><?php if($error): ?><div class="error" role="alert"><?=e($error)?></div><?php endif; ?><form method="post"><?=csrf()?><input type="hidden" name="action" value="login"><label>Email<input type="email" name="email" maxlength="190" autocomplete="username" required></label><label>Password<input type="password" name="password" maxlength="512" autocomplete="current-password" required></label><button class="button" type="submit">Sign in ↗</button></form><p class="fine">Sessions expire after 30 minutes of inactivity.</p></main>
<?php else: ?><div class="wrap admin-layout"><aside class="admin-sidebar"><?php foreach($tabs as $key=>$label): ?><a class="<?=$tab===$key?'selected':''?>" href="?tab=<?=$key?>"><?=e($label)?></a><?php endforeach; ?><form method="post"><?=csrf()?><input type="hidden" name="action" value="logout"><button class="button small" type="submit">Sign out</button></form></aside><main class="admin-panel"><div class="eyebrow">YOUR WEBSITE, YOUR CONTENT</div><h1><?=e($tabs[$tab])?></h1><?php if($error): ?><div class="error" role="alert"><?=e($error)?></div><?php endif; ?><?php if($notice): ?><div class="notice" role="status"><?=e($notice)?></div><?php endif; ?>
<?php if($tab==='dashboard'): ?><p>Manage the content visitors see and follow up on new enquiries.</p><div class="stat-grid"><?php foreach(['projects'=>'Projects','reviews'=>'Reviews','enquiries'=>'Enquiries'] as $table=>$label): ?><a class="stat" href="?tab=<?=$table?>"><strong><?=count(rows($table))?></strong><span><?=$label?> (latest 100)</span></a><?php endforeach; ?></div><div class="admin-note">Publish only genuine project photographs and customer reviews you have permission to share. Social publishing is not connected; use Posts & social to prepare captions and share website links.</div><p>Contact email and database credentials are configured on the server. Back up the database and uploads regularly.</p>
<?php elseif($tab==='content'): ?><form method="post"><?=csrf()?><input type="hidden" name="action" value="content"><?php $fields=['home_title'=>['Homepage headline',"Powering progress.\nFrom the rooftop up."],'home_intro'=>['Homepage introduction','Solar rooftop solutions and electrical infrastructure, built around your needs. Serving homes, businesses and communities across Odisha.'],'about_body'=>['About page introduction','Aanshu Engineering Solution Private Limited works across solar rooftop supply and electrical distribution construction. Based in Atri, Baghamari, Khordha, we serve solar customers throughout Odisha.'],'service_solar'=>['Solar service description','Solar panel supply, rooftop installation and associated services for homes and businesses across Odisha.'],'service_line'=>['Overhead line description','Construction of new 11 kV and 33 kV overhead electrical distribution lines.'],'service_transformer'=>['Transformer description','Installation of new distribution transformers as part of electrical distribution projects.'],'service_underground'=>['Underground line description','Construction of new 11 kV and 33 kV underground electrical distribution lines.']]; foreach($fields as $key=>$data): ?><label><?=e($data[0])?><textarea name="<?=$key?>" rows="3" maxlength="6000" required><?=e(content($key,$data[1]))?></textarea></label><?php endforeach; ?><p class="fine">Plain text only. HTML is displayed as text to protect the website.</p><button class="button" type="submit">Save page content ↗</button></form>
<?php elseif($tab==='projects'): ?><form method="post" enctype="multipart/form-data"><?=csrf()?><input type="hidden" name="action" value="project"><label>Project title<input name="title" maxlength="160" required></label><label>Project description<textarea name="description" maxlength="4000" rows="3" required></textarea></label><label>Installation photo (JPG, PNG or WebP, up to 5 MB)<input name="image" type="file" accept="image/jpeg,image/png,image/webp" required></label><p class="fine">Photographs are re-encoded as JPEG to remove metadata and embedded content.</p><button class="button" type="submit">Publish project ↗</button></form><?php foreach(rows('projects') as $item): ?><article class="admin-record"><img src="uploads/<?=e(basename($item['image']))?>" alt="<?=e($item['title'])?>"><h3><?=e($item['title'])?></h3><p><?=e($item['description'])?></p><?php editForm('projects',$item); deleteForm('projects',$item['id']); ?></article><?php endforeach; ?>
<?php elseif($tab==='reviews'): ?><form method="post"><?=csrf()?><input type="hidden" name="action" value="review"><label>Customer display name<input name="customer" maxlength="100" required></label><label>Customer’s feedback<textarea name="body" maxlength="4000" rows="4" required></textarea></label><label><input type="checkbox" name="consent" value="1" required> I have this customer’s permission to publish their name and feedback.</label><button class="button" type="submit">Publish review ↗</button></form><?php foreach(rows('reviews') as $item): ?><article class="admin-record"><h3><?=e($item['customer'])?></h3><p><?=e($item['body'])?></p><?php editForm('reviews',$item); deleteForm('reviews',$item['id']); ?></article><?php endforeach; ?>
<?php elseif($tab==='posts'): ?><div class="admin-note"><strong>Social accounts are not connected.</strong> Publishing below adds an update to this website. You can copy a caption for Instagram or share your website link on Facebook. Automatic Instagram/Facebook publishing requires an approved Meta app, eligible linked accounts, tokens, and a public HTTPS website.</div><form method="post"><?=csrf()?><input type="hidden" name="action" value="post"><label>Post title<input name="title" maxlength="160" required></label><label>Post text<textarea name="body" rows="5" maxlength="6000" required></textarea></label><button class="button" type="submit">Publish website update ↗</button></form><?php foreach(rows('posts') as $item): ?><article class="admin-record"><h3><?=e($item['title'])?></h3><p class="caption"><?=nl2br(e($item['body']))?></p><div class="admin-actions"><button class="button small copy-caption" data-caption="<?=e($item['title']."\n\n".$item['body'])?>" type="button">Copy caption</button><a class="button small facebook-share" href="https://www.facebook.com/sharer/sharer.php" target="_blank" rel="noopener noreferrer">Share updates link ↗</a></div><?php editForm('posts',$item); deleteForm('posts',$item['id']); ?></article><?php endforeach; ?>
<?php elseif($tab==='enquiries'): ?><p>Enquiries are stored here. Follow up by email or phone. Delete personal details when they are no longer needed.</p><?php $items=rows('enquiries'); if(!$items): ?><p>No enquiries yet.</p><?php endif; foreach($items as $item): ?><article class="admin-record"><div class="eyebrow"><?=e($item['created_at'])?></div><h3><?=e($item['name'])?> · <?=e($item['service'])?></h3><p><?=nl2br(e($item['message']))?></p><p><a href="mailto:<?=e($item['email'])?>"><?=e($item['email'])?> ↗</a><br><?=e($item['phone'])?></p><?php deleteForm('enquiries',$item['id']); ?></article><?php endforeach; ?>
<?php elseif($tab==='account'): ?><form method="post"><?=csrf()?><input type="hidden" name="action" value="password"><label>Current password<input name="current_password" type="password" maxlength="512" autocomplete="current-password" required></label><label>New password (at least 14 characters)<input name="new_password" type="password" minlength="14" maxlength="512" autocomplete="new-password" required></label><button class="button" type="submit">Change password ↗</button></form><?php endif; ?></main></div><?php endif; ?></body></html>
<?php function deleteForm(string $table,int $id): void { ?><form method="post" class="delete-form"><?=csrf()?><input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="<?=e($table)?>"><input type="hidden" name="id" value="<?=$id?>"><button class="delete" type="submit">Delete</button></form><?php } ?>

<?php function editForm(string $table,array $item): void { $title=$table==='reviews'?'customer':'title'; $body=$table==='projects'?'description':'body'; ?>
<details class="record-editor"><summary>Edit content</summary><form method="post"><?=csrf()?><input type="hidden" name="action" value="edit"><input type="hidden" name="table" value="<?=e($table)?>"><input type="hidden" name="id" value="<?=(int)$item['id']?>"><label><?=e(ucfirst($title))?><input name="<?=e($title)?>" maxlength="<?=$title==='customer'?100:160?>" required value="<?=e($item[$title])?>"></label><label><?=e(ucfirst($body))?><textarea name="<?=e($body)?>" rows="4" maxlength="<?=$table==='posts'?6000:4000?>" required><?=e($item[$body])?></textarea></label><button class="button small" type="submit">Save changes ↗</button></form></details>
<?php } ?>