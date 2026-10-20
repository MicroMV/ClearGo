<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

// PHP's development server ignores .htaccess, so this checks the PHP guards alone.
$root = dirname(__DIR__);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorText);
if ($socket === false) { throw new RuntimeException($errorText); }
$address = stream_socket_get_name($socket, false);
fclose($socket);
$log = tempnam(sys_get_temp_dir(), 'cleargo-test-http-');
if ($log === false) { throw new RuntimeException('Cannot create the test server log.'); }
$environment = getenv();
// Any application/database initialization would be an error, not a school-data operation.
$environment['DB_HOST'] = '127.0.0.1';
$environment['DB_PORT'] = '1';
$environment['DB_DATABASE'] = 'http_test_guard_must_not_connect';
$environment['DB_USERNAME'] = 'http_guard_test';
$environment['DB_PASSWORD'] = '';
unset($environment['APP_BASE_URL']);
$server = null;
$checks = 0;

try {
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', $root],
        [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root, $environment);
    if (!is_resource($server)) { throw new RuntimeException('Cannot start the isolated PHP server.'); }
    fclose($pipes[0]);
    $started = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $errorNumber, $errorText, 0.1);
        if ($probe !== false) { fclose($probe); $started = true; break; }
        usleep(100000);
    }
    if (!$started) { throw new RuntimeException('The isolated server did not start.'); }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') { continue; }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(__DIR__) + 1));
        $path = implode('/', array_map('rawurlencode', explode('/', $relative)));
        foreach (['GET', 'POST'] as $method) {
            $context = stream_context_create(['http' => [
                'method' => $method, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 5,
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $method === 'POST' ? 'action=ready&notes=Must+not+execute' : '',
            ]]);
            $body = file_get_contents('http://' . $address . '/tests/' . $path, false, $context);
            $headers = $http_response_header ?? [];
            if ($body === false || !preg_match('/\s403\s/', $headers[0] ?? '')
                || !in_array(trim($body), ['', 'Forbidden'], true)
                || preg_grep('/^Set-Cookie:/i', $headers)) {
                throw new RuntimeException($relative . ' did not reject ' . $method . ' before application initialization.');
            }
            $checks++;
        }
    }
    echo "PASS: {$checks} unauthenticated GET/POST requests denied by PHP guards without .htaccess.\n";
    echo "No test body, session initialization or database connection was reached.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (is_file($log)) { unlink($log); }
}
