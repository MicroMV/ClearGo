<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
requireRole('ACCOUNTING_STAFF');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$query = getDatabaseConnection()->prepare('SELECT receipt_code FROM accounting_receipts WHERE id=:id');
$query->execute(['id' => $id ?: 0]);
$code = (string) $query->fetchColumn();
if (!preg_match('/^PORTAL-[a-f0-9]{48}$/D', $code)) { http_response_code(404); exit; }
$path = __DIR__ . '/../storage/private/accounting-receipts/' . $code . '.image';
if (!is_file($path)) { http_response_code(404); exit; }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) { http_response_code(404); exit; }
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);