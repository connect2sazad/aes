<?php
function saveProject(array $values,int $id): int {
    $staged=[];
    try {
        db()->beginTransaction();
        $old=$id?query('SELECT * FROM projects WHERE id=? FOR UPDATE',[$id])->fetch():null;
        if($id && !$old) throw new InvalidArgumentException('Project no longer exists.');
        $existing=$id?projectPhotos($id):[];
        $remove=$_POST['remove_photos'] ?? []; $alts=$_POST['alt'] ?? [];
        if(!is_array($remove) || !is_array($alts)) throw new InvalidArgumentException('Invalid photo selection.');
        $ids=array_map('strval',array_column($existing,'id'));
        foreach($remove as $photoId) if(!is_scalar($photoId) || !in_array((string)$photoId,$ids,true)) throw new InvalidArgumentException('Photo does not belong to this project.');
        foreach($alts as $photoId=>$alt) if(!in_array((string)$photoId,$ids,true) || !is_string($alt) || mb_strlen($alt)>240) throw new InvalidArgumentException('Invalid photo alt text (maximum 240 characters).');
        $keep=array_values(array_filter($existing,fn($p)=>!in_array((string)$p['id'],array_map('strval',$remove),true)));
        $incoming=uploadedProjectPhotos();
        if(count($keep)+count($incoming)<1 || count($keep)+count($incoming)>12) throw new InvalidArgumentException('Each project must have 1–12 photos.');
        $cover=(string)($_POST['cover_photo'] ?? '');
        if($cover!=='' && !in_array($cover,$ids,true)) throw new InvalidArgumentException('Invalid cover photo.');
        foreach($incoming as $file) $staged[]=uploadPhoto($file);
        $coverName=$keep[0]['photo'] ?? $staged[0];
        foreach($keep as $p) if((string)$p['id']===$cover || ($cover==='' && $p['photo']===($old['photo'] ?? ''))) $coverName=$p['photo'];
        $values['photo']=$coverName;
        if($id) query('UPDATE projects SET title=?,category=?,location=?,description=?,published=?,photo=? WHERE id=?',[...array_values($values),$id]);
        else {query('INSERT INTO projects (title,category,location,description,published,photo) VALUES (?,?,?,?,?,?)',array_values($values));$id=(int)db()->lastInsertId();}
        foreach($remove as $photoId) query('DELETE FROM project_photos WHERE id=? AND project_id=?',[$photoId,$id]);
        foreach($alts as $photoId=>$alt) query('UPDATE project_photos SET alt_text=? WHERE id=? AND project_id=?',[trim($alt),$photoId,$id]);
        foreach($staged as $i=>$photo) query('INSERT INTO project_photos (project_id,photo,alt_text,sort_order) VALUES (?,?,?,?)',[$id,$photo,$values['title'],count($existing)+$i]);
        audit('Saved project #'.$id.' with '.(count($keep)+count($staged)).' photos');db()->commit();return $id;
    } catch(Throwable $error) {
        if(db()->inTransaction()) db()->rollBack();
        foreach($staged as $file) if($file && is_file(ROOT.'/storage/photos/'.$file)) unlink(ROOT.'/storage/photos/'.$file);
        throw $error;
    }
}
