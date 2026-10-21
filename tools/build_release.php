<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Forbidden');}
$root=realpath(dirname(__DIR__));$base=$root.'/.tmp/releases';
if(!is_dir($base))mkdir($base,0700,true);$base=realpath($base);
if($base===false||!str_starts_with(str_replace('\\','/',$base),str_replace('\\','/',$root).'/.tmp/'))throw new RuntimeException('Unsafe release directory.');
$name='ClearGo-'.(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Ymd-His').'-'.bin2hex(random_bytes(4));
$destination=$base.'/'.$name;mkdir($destination,0700);$files=[];
$copy=static function(string $relative)use($root,$destination,&$files):void{
 $original=realpath($root.'/'.$relative);
 if($original===false||!str_starts_with(str_replace('\\','/',$original),str_replace('\\','/',$root).'/'))throw new RuntimeException('Unsafe release source.');
 $target=$destination.'/'.$relative;if(!is_dir(dirname($target)))mkdir(dirname($target),0700,true);
 if(!copy($original,$target))throw new RuntimeException('Release copy failed.');$files[$relative]=hash_file('sha256',$target);
};
foreach(['accounting','assets','auth','bookstore','clearance','clinic','comlab','config','dean','errors','facilities','library','organization','registrar','saso','student','student-council','lib']as$directory){
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory,FilesystemIterator::SKIP_DOTS))as$file){
  if(!$file->isFile()||preg_match('/\.local\.php$|^\.env(?:\.|$)|\.(?:log|bak|sql|gz|tmp)$/i',$file->getFilename()))continue;
  $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));$copy($relative);
 }
}
foreach(['.htaccess','index.php','login.php','logout.php','forgot_password.php','manifest.webmanifest','connection-worker.js',
 'tools/.htaccess','tools/auth_mail_worker.php','tools/deployment_check.php','tools/maintenance.php','database/.htaccess']as$file)$copy($file);
foreach(glob($root.'/database/migrations/*.sql')as$file)$copy('database/migrations/'.basename($file));
foreach(['storage','storage/private','storage/sessions','storage/backups']as$directory){
 $path=$destination.'/'.$directory;if(!is_dir($path))mkdir($path,0700,true);file_put_contents($path.'/.htaccess',"Require all denied\n");$files[$directory.'/.htaccess']=hash_file('sha256',$path.'/.htaccess');
}
ksort($files);$zipPath=$base.'/'.$name.'.zip';
if(class_exists('ZipArchive')){
 $zip=new ZipArchive();if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Unable to create release ZIP.');
 foreach($files as$file=>$hash)if(!$zip->addFile($destination.'/'.$file,$file))throw new RuntimeException('Unable to add release file.');
 if(!$zip->close())throw new RuntimeException('Unable to finish release ZIP.');
}else{
 // Reuse the application's existing ZIP writer when the optional zip extension is absent.
 require_once $root.'/registrar/student_import_functions.php';$entries=[];
 foreach($files as$file=>$hash)$entries[$file]=file_get_contents($destination.'/'.$file);
 if(file_put_contents($zipPath,createStoredZipArchive($entries))===false)throw new RuntimeException('Unable to create release ZIP.');
}
$manifest=$base.'/'.$name.'-manifest.json';file_put_contents($manifest,json_encode($files,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode(['zip'=>$zipPath,'manifest'=>$manifest,'files'=>count($files),'bytes'=>filesize($zipPath)],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
