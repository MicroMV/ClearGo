<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$root = dirname(__DIR__, 3);
$paths = ['/tests/', '/tests/README.md', '/tests/.htaccess'];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/tests', FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
        $paths[] = '/tests/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root . '/tests') + 1));
    }
}
$evidence = [];
foreach ($paths as $path) {
    $context = stream_context_create(['http' => ['method' => 'HEAD', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
    $result = file_get_contents('http://localhost/ClearGo' . $path, false, $context);
    $headers = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $headers[0] ?? '', $match);
    $status = (int) ($match[1] ?? 0);
    if ($result === false || $status !== 403) { throw new RuntimeException($path . ' was not denied by Apache: ' . $status); }
    $evidence[] = ['path' => $path, 'method' => 'HEAD', 'status' => $status];
}
file_put_contents(__DIR__ . '/apache-results.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo 'PASS: Apache denied all ' . count($evidence) . " test-directory paths with HTTP 403.\n";
