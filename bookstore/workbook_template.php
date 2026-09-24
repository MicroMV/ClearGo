<?php
declare(strict_types=1);
require_once __DIR__.'/../auth/auth_check.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/workbook_functions.php';
requireRole('BOOKSTORE_STAFF');
try{$workbook=createBookstoreWorkbookXlsx(getDatabaseConnection());}
catch(Throwable $exception){error_log('Bookstore workbook export failed: '.$exception->getMessage());http_response_code(500);exit('Unable to create the Bookstore workbook.');}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="cleargo-bookstore-clearance.xlsx"');
header('Content-Length: '.strlen($workbook)); header('Cache-Control: no-store'); echo $workbook;
