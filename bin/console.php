#!/usr/bin/env php
<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
try{
    if(is_file(updaterPath('maintenance'))&&getenv('PULSE_UPDATE_WORKER')!=='1')throw new RuntimeException('Update maintenance in progress. Retry after completion.');
    boot();
    $command=$argv[1]??'help';
    if($command==='health'){echo jsonValue(applicationHealth()).PHP_EOL;
    }elseif($command==='setup-token'){
        if(installed())throw new RuntimeException('Setup already completed.');
        echo setupToken().PHP_EOL;
    }elseif($command==='migrate'){
        echo 'Schema ready. PULSE '.version().PHP_EOL;
    }elseif($command==='backup'){
        if(!installed())throw new RuntimeException('Complete initial setup first.');
        echo createBackup($argv[2]??'manual').PHP_EOL;
    }elseif($command==='check-updates'){
        if(!installed()){echo "Setup not completed; no update check.\n";exit(0);}
        $result=checkUpdates();echo jsonValue($result).PHP_EOL;
        if(!empty($result['error']))exit(1);
    }elseif($command==='snapshot'){
        if(!installed())throw new RuntimeException('Complete initial setup first.');
        snapshot($argv[2]??'daily');echo "Snapshot saved.\n";
    }else{
        echo "PULSE commands:\n  setup-token\n  migrate\n  backup [daily|weekly|manual]\n  snapshot [daily|weekly]\n  check-updates\n";
        if($command!=='help')exit(1);
    }
}catch(Throwable $error){fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1);}

