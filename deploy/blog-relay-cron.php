<?php
// Select PHP (not HTTP/HTTPS) in the hosting Cron panel. Keep outside web root.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/blog-sync.php';
ini_set('display_errors','0');
try{pulseRelayMain([__FILE__,'--config',__DIR__.'/config.php']);}
catch(Throwable $e){fwrite(STDERR,'PULSE relay: '.($e instanceof RuntimeException?$e->getMessage():'Invalid response or hosting configuration.').PHP_EOL);exit(1);}
