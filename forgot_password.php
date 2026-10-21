<?php
declare(strict_types=1);
require_once __DIR__ . '/auth/auth_check.php';
// Preserve old bookmarks while keeping recovery inside the login modal.
redirectTo('/login.php?recovery=1');
