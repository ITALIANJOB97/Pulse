<?php
declare(strict_types=1);

function hallMembers(): array { return array_values(content('hall','all')); }
function hallAction(string $action, ?DateTimeImmutable $now=null): void {
    $id=(int)inputNumber('id',1,PHP_INT_MAX);
    $story=$action==='hall_delete'?[]:translationsFromPost('story',500);
    $name=$action==='hall_save'?inputText('name',100):'';
    if($action==='hall_save'&&$name==='')throw new DomainException(t('invalid_input'));
    db()->exec('BEGIN IMMEDIATE');
    try {
        $members=hallMembers();
        if($action==='employee_archive'){
            $q=db()->prepare('SELECT * FROM employees WHERE id=?');$q->execute([$id]);$person=$q->fetch();
            if(!$person)throw new DomainException(t('invalid_input'));
            foreach($members as $member)if((int)$member['id']===$id)throw new DomainException(t('invalid_input'));
            $now=($now??new DateTimeImmutable())->setTimezone(new DateTimeZone('Europe/Zurich'));
            $members[]=['id'=>$id,'name'=>$person['name'],'photo'=>$person['photo'],'role'=>$person['role'],
                'story'=>$story,'added_at'=>$now->getTimestamp(),'priority_until'=>$now->modify('+7 days')->getTimestamp()];
            // Photo files and historical snapshots remain available after leaving the team.
            db()->prepare('DELETE FROM employees WHERE id=?')->execute([$id]);
        }else{
            $index=null;foreach($members as $i=>$member)if((int)$member['id']===$id){$index=$i;break;}
            if($index===null)throw new DomainException(t('invalid_input'));
            if($action==='hall_delete')array_splice($members,$index,1);
            elseif($action==='hall_save'){
                $photo=uploadImage('photo','employee',$name);
                $members[$index]['name']=$name;$members[$index]['story']=$story;
                if($photo)$members[$index]['photo']=$photo;
                // Editing a dedication never restarts the first-week priority.
            }else throw new DomainException(t('invalid_input'));
        }
        saveContent('hall','all',array_values($members));db()->exec('COMMIT');
    }catch(Throwable $error){db()->exec('ROLLBACK');throw $error;}
}
