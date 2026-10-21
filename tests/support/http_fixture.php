<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

/** Fetch the token from the signed-in page after login rotates the CSRF/session tokens. */
function signedInFixtureCsrf(CurlHandle $client, string $base): string
{
    curl_setopt_array($client, [CURLOPT_URL => $base . '/auth/profile.php', CURLOPT_HTTPGET => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTPHEADER => []]);
    try {
        $body = curl_exec($client);
        if (!is_string($body) || (int) curl_getinfo($client, CURLINFO_HTTP_CODE) !== 200
            || !preg_match('/name="csrf_token" value="([^"]+)"/', $body, $match)) { throw new RuntimeException('Missing signed-in CSRF token.'); }
        return html_entity_decode($match[1]);
    } finally { curl_setopt($client, CURLOPT_FOLLOWLOCATION, false); }
}
