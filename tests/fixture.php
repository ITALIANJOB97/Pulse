<?php
declare(strict_types=1);
// Test-only CLI helper. Not available through the web root.
if(PHP_SAPI!=='cli'||getenv('PULSE_TEST_MODE')!=='1'||!str_contains((string)getenv('PULSE_STORAGE'),'pulse-qa-'))exit(1);
require dirname(__DIR__).'/app/bootstrap.php';boot();
$command=$argv[1]??'';
if($command==='image'){
    $image=imagecreatetruecolor(300,220);
    imagefill($image,0,0,imagecolorallocate($image,232,203,85));
    imagejpeg($image,storage('fixture.jpg'),85);
}elseif($command==='available'){
    setSetting('update_state',['release'=>validateRelease(['app'=>'pulse-dietikon','version'=>'0.3.0','notes'=>['en'=>'Test release']]),'checked_at'=>gmdate('c')]);
}elseif($command==='installed'){
    setSetting('installed_version','0.0.9');syncVersion();
}elseif($command==='clear-updates'){
    setSetting('update_state',[]);setSetting('version_event',null);
}
