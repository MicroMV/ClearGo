# Signature recovery and mobile paper preview — 20 October 2026

Both follow-up issues are resolved.

## Recovering a Ready account with no selected signature

1. The Registrar opens **Staff Accounts**. Eligible active staff accounts show **Allow signature setup**.
2. The Registrar enters a reason and authorizes setup for that account and the current semester.
3. The staff member opens **My Profile** and draws/saves a signature or selects an existing signature they own.

Permission expires after 24 hours and is consumed by successful setup. Invalid submissions do not consume permission. Normal Ready locks remain in place; recovery cannot edit/delete signatures or replace an existing selection. Only the Registrar can authorize recovery, and an account with a selected signature is ineligible. Permission does not carry to another semester or staff account.

Readiness, office configuration, student approvals and captured signatures remain unchanged. Recovery does not attach a new signature to old unsigned approvals. Authorization and completion have separate audit events; the staff member also receives a notification.

Migration `database/migrations/035_signature_recovery.sql` adds the permission table and was applied locally. Existing records were not updated, and no live recovery grants were issued. Apply this migration when deploying these changes elsewhere. The temporary debug reset includes these semester-specific permissions.

## Reading the clearance paper on mobile

The preview uses the same drawing operations, coordinates, text, logo and signature images as the downloadable PDF. A vector paper view replaces dependence on a mobile browser's embedded PDF viewer. The PDF download remains available, and a rendering regression confirms that enabling the preview does not alter PDF output bytes.

Mobile opens at **Read size** with horizontal/vertical scrolling. **+**, **−**, **Fit page** and **Read size** control zoom. Desktop starts with the paper fitted to the viewer. These controls work in the dashboard dialog and standalone preview. Escape from inside the viewer closes the dashboard dialog and returns focus to its trigger. Ownership, open-semester access, private caching headers and the loading indicator remain enforced.

## Verification

| Check | Result |
|---|---:|
| CCS completion, signature recovery and office workflow integration | 244 passed |
| PDF output equivalence, escaped SVG text and image rendering | 6 passed |
| Debug backup/reset regressions | 128 passed |
| Signature drawing, editor, Ready locks and recovery profile browser checks | 92 passed |
| Paper preview, mobile zoom, loading and keyboard browser checks | 66 passed |
| Live student progress and upload preservation browser checks | 22 passed |

Database checks used disposable schemas and generated records. Browser checks used synthetic response snapshots at desktop/mobile widths. PHP/JavaScript syntax checks and `git diff --check` passed. Desktop and mobile preview screenshots are in `.tmp/clearance-preview-check/`.
