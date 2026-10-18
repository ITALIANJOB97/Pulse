<?php
// Development only.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$file=realpath(__DIR__.$path);
if($file&&str_starts_with($file,__DIR__.DIRECTORY_SEPARATOR)&&is_file($file)&&(str_starts_with($path,'/assets/')||str_starts_with($path,'/guide/'))) return false;
require __DIR__.'/index.php';

