# Clearance signing fixes — 20 October 2026

The High finding and both Medium findings from the student signing simulation are fixed under the requested policy.

## Signature required before Ready and approval

- All ten offices with a Ready status require the acting staff account to have a selected signature, printed name and position before confirming Ready.
- My Profile supports editing the name, position and drawing, and deleting saved signatures. An empty drawing during an edit preserves the existing image.
- Saving, selecting, editing and deleting signatures are blocked while the office is Ready, including stale POST requests. Undo Ready restores management during semester preparation.
- Deleting the selected signature removes its selection. Another signature must be selected before Ready or approval.
- The temporary debug Ready all action also requires configured signatures; it does not bypass this policy.
- Approval captures the approving account's signature within the same transaction. Missing signatures roll back the approval and its other changes.
- Registrar completion rejects approved offices with missing or mismatched signature snapshots. The Registrar has no office Ready status; its own signature is required when it approves clearance.

## No retroactive signatures

- Saving or selecting a signature does not attach it to older unsigned approvals.
- PDF downloads read captured signatures without creating or changing them.
- Editing, selecting or deleting saved signatures preserves the original name, position and image already captured on earlier approvals.

## Student progress updates

- Successful notification polls refresh the student's clearance counts, progress ring, office cards, next action and requirement dialogs without reloading the page.
- The current office filter remains selected. Newly unlocked requirement controls work immediately.
- Active dialogs and draft forms, selected uploads and camera work are preserved. Safe dialog replacements are applied after closing.
- Responses for another student or semester, redirected sessions and failed refreshes are ignored. Archived semesters do not poll live clearance content.

## Verification

| Suite | Result |
|---|---:|
| `tests/ccs_completion_workbook_test.php` (includes the Dean and complete office workflow regressions) | 223 passed |
| `tests/attendance_sanctions_test.php` | 97 passed |
| `tests/debug_console_test.php` | 126 passed |
| `tests/signature_pad_browser_test.mjs` | 83 passed |
| `tests/clearance_preview_browser_test.mjs` | 54 passed |
| `tests/clearance_live_browser_test.mjs` | 22 passed |

PHP and JavaScript syntax checks and `git diff --check` passed. Database suites used disposable schemas and generated test records; browser suites used synthetic response snapshots and local assets. Existing school readiness, approvals and signatures were not repaired or reset.

## Existing records

Update: the Registrar can now authorize one-time signature setup for an active staff account whose Ready office is missing a selected signature. This preserves readiness and earlier approvals; it does not enable editing/deletion or replace an existing selection. See [recovery and mobile preview verification](../signature-recovery-and-mobile-preview/VERIFICATION.md). Historical unsigned approvals remain unsigned and are blocked from Registrar completion rather than receiving a later signature automatically.
