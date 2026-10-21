<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403); exit('Forbidden');
}

/** Server environment takes precedence over the optional private local configuration. */
function appSetting(string $name, string $default = ''): string
{
    $value = getenv($name);
    if ($value !== false) { return $value; }
    static $local = null;
    if ($local === null) {
        $path = __DIR__ . '/environment.local.php';
        $local = is_file($path) ? require $path : [];
        if (!is_array($local)) { throw new RuntimeException('Invalid private application configuration.'); }
    }
    return isset($local[$name]) && is_scalar($local[$name]) ? (string) $local[$name] : $default;
}

function productionEnvironment(): bool
{
    return in_array(strtolower(appSetting('APP_ENV', 'development')), ['production', 'staging'], true);
}
