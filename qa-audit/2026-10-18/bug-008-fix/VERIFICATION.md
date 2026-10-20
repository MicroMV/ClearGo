# BUG-008 — Git metadata HTTP access blocked

Verified on local XAMPP Apache on 18 October 2026, Asia/Manila.

The original `HEAD /ClearGo/.git/config` returned **200** before this change. The application root `.htaccess` now enables rewriting and denies any path segment named `.git`, including its descendants, with a case-insensitive forbidden response. This also covers a `.git` file in a worktree and nested repositories. No repository files or Git settings were changed.

## Local Apache verification

All probes below used HEAD requests without a login. No repository file contents were requested or downloaded.

| Path under `/ClearGo` | Result after change |
|---|---|
| `/.git` | 403 |
| `/.git/` | 403 |
| `/.git/config` | 403 |
| `/.git/HEAD` | 403 |
| `/.git/index` | 403 |
| `/.git/objects/` | 403 |
| `/.git/refs/heads/main` | 403 |
| `/.GIT/config` | 403 |
| `/.%67it/config` | 403 |
| `/.git/config?download=1` | 403 |

Login, shared dashboard CSS, Dean completion CSS and the server-status endpoint all returned **200**. An unauthenticated Dean dashboard request still returned **303** to `/ClearGo/login.php`. `httpd.exe -t` reported **Syntax OK**. The effective per-directory rule was verified by the live requests.

## Deployment

Keep the root `.htaccess` in the deployment. This rule requires Apache `mod_rewrite` and permission for FileInfo overrides; local XAMPP already enables the module and uses `AllowOverride All` for its web root. Verify the original URL returns 403 or 404 on each deployed host. Exclude `.git` from production packages wherever possible. Hosts that do not honor Apache `.htaccess` need the equivalent deny rule in their server configuration.

No student records, approvals, uploads or database data were changed for this fix. The check establishes denial on this local Apache deployment; it does not establish whether any prior visitor accessed repository data.
