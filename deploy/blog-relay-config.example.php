<?php
// Public relay: no credential required. Keep configuration outside the web root; chmod 600.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
return [
    'web_root'=>'/home/ACCOUNT/public_html',
    'public_dir'=>'/home/ACCOUNT/public_html/myfile/Pulse',
];
