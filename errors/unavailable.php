<?php
// Included by displayDatabaseUnavailable(); this view needs no database connection.
if (!function_exists('connectionStatusAssets')) {
    http_response_code(404);
    exit;
}
$retryUrl = $_SERVER['REQUEST_URI'] ?? applicationUrl('/login.php');
if (!str_starts_with($retryUrl, '/') || str_starts_with($retryUrl, '//')) { $retryUrl = applicationUrl('/login.php'); }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Temporarily unavailable | ClearGo</title>
    <?= connectionStatusAssets(true) ?>
</head>
<body class="connection-error-body">
    <main id="connection-screen" class="connection-screen connection-error-page" data-connection-kind="database" aria-labelledby="connection-title">
        <section class="connection-card">
            <div class="connection-visual">
                <div class="connection-brand"><img class="connection-brand-logo" src="<?= escapeHtml(clearGoLogoUrl()) ?>" alt="The Lewis College logo">ClearGo</div>
                <div class="connection-art" aria-hidden="true"></div>
                <div class="connection-school"><strong>The Lewis College</strong><span>Student Clearance Portal</span></div>
            </div>
            <div class="connection-content">
                <span class="connection-status-label">Please try again</span>
                <h1 id="connection-title">ClearGo is temporarily unavailable</h1>
                <p id="connection-message">Please wait a moment, then try again.</p>
                <div class="connection-help"><span class="connection-help-icon" aria-hidden="true">i</span><p>If you were saving a change, check the record when ClearGo is available before trying again.</p></div>
                <a id="connection-retry" class="connection-retry" href="<?= escapeHtml($retryUrl) ?>"><span class="connection-retry-icon" aria-hidden="true">&#8635;</span>Try again</a>
                <p id="connection-feedback" class="connection-feedback" role="status" aria-live="polite"></p>
                <div class="connection-footer"><span aria-hidden="true"></span>We'll try again automatically when ClearGo is available.</div>
            </div>
        </section>
    </main>
</body>
</html>
