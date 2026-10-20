# Developer tests

Run PHP tests from the command line, for example:

```powershell
& C:\xamppl\php\php.exe tests/staff_navigation_test.php
```

These scripts are development tools. Several integration tests write to their configured database. Use a disposable database with synthetic records when running them; do not run them against school data.

Exclude the entire `tests/` directory from production deployment packages. If it is present on an Apache host, keep `.htaccess` with it and allow that directory's access-control directives in the server configuration. Other web servers must deny `/tests/` and all descendants in their own configuration.

Every PHP entry point in this directory must reject non-CLI execution before including application code or touching files, sessions or databases. This guard is also required for new tests, even with the Apache denial rule present. Do not remove it to preview a test in a browser.

`attendance_sanctions_test.php` creates and removes its own disposable schema using the configured database as a schema template. Apply migration `034_attendance_sanctions.sql` first. It tests immediate QR check-in, absence assignment, separate Organization/Council photo batches, protected images, review and resubmission, and manual/bulk clearance. Then run `node tests/attendance_sanctions_browser_test.mjs` to check the synthetic response snapshots at desktop and mobile widths; screenshots and results are saved under `.tmp/attendance-sanctions-check/browser/`. The browser test uses the installed Microsoft Edge.

Also exclude `.tmp/` from production packages: it contains scratch scripts and generated previews. The application's root `.htaccess` denies both `.git` and `.tmp` path segments; keep that root file in deployments and enable Apache `mod_rewrite` with FileInfo overrides. Local `.tmp/.htaccess` provides an additional access denial. Any PHP helper added to `.tmp/` must reject non-CLI execution before doing any work. Other web servers need equivalent deny rules.
