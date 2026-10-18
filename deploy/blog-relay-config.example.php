<?php
// Keep this file outside the web root; chmod 600. Never commit the real token.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
return [
    'web_root'=>'/home/ACCOUNT/public_html',
    'public_dir'=>'/home/ACCOUNT/public_html/myfile/Pulse',
    'github_token'=>getenv('PULSE_GITHUB_TOKEN'), // Or paste the read-only token in this PRIVATE file only.
];
