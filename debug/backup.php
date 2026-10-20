<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
requireDebugAccess();
require_once __DIR__ . '/functions.php';
$name = $_GET['file'] ?? null;
if (!is_string($name) || !preg_match('/\Adebug-[0-9]{8}-[0-9]{6}-[a-f0-9]{16}\.sql\.gz\z/',$name)) { http_response_code(404); exit; }
$path = debugBackupDirectory() . '/' . $name;
if (!is_file($path) || is_link($path)) { http_response_code(404); exit; }
header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
