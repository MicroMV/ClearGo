<?php
// Match the original Apache denial rules when using PHP's isolated built-in server.
if(PHP_SAPI!=='cli-server'){http_response_code(403);exit;}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('~^/(?:config|database|storage/private|storage/sessions)(?:/|$)~',$path)){http_response_code(403);exit('Forbidden');}
return false;
