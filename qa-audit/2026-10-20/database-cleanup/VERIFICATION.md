# Database cleanup - 20 October 2026

Migration 036 was applied to the local `lewis_clearance` database after a private full backup.

## Removed

- `students.section`: the user explicitly confirmed removing this field entirely. All 2,317 existing values were `N/A`. Student creation/update, roster import, student/Registrar/office/attendance queries and clearance-document queries no longer reference the column. Student Account now displays **Year level**. Existing test fixtures were updated for the new schema.
- `accounting_receipt_verifications`: an empty table for the retired automatic AI receipt-photo verifier. There were no live application readers/writers or incoming foreign-key dependencies. Manual Accounting assessment review continues to use `accounting_receipts`; that table and its current data were preserved. The disabled debug helper's table list was updated without enabling the debug console.

No other tables were dropped. Every one of the 43 retained tables has application references. Empty tables supporting current accounting obligations, document requirements, attendance sanctions, recovery permissions and other workflows were retained. This is a removal of confirmed obsolete structures, not a prediction about every possible future use of a column.

Historical migrations remain unchanged; fresh migration sequences apply 036 after 035. A fresh export of the current database already includes these changes and must not have the column-drop statement applied again.

## Data protection

- Full backup: `storage/backups/debug-console/debug-20261020-222030-470c9ecb1a75dce2.sql.gz` (1,335,167 bytes). The compressed SQL completion marker was checked before DDL.
- Before/after ordered data fingerprints matched for all 43 retained tables, excluding only the intentionally removed section field. Accounts, student IDs and names, enrollments, clearance decisions, signatures, audit records and all other retained data were unchanged at migration time.
- Preflight refused non-placeholder section data, a non-empty retired table or unexpected incoming references.
- HEAD requests to the backup path and disabled `/debug/` returned 403. The debug enable flag remains absent.
- `application-results.json` contains the retained-data comparison and backup reference; it contains counts/hashes rather than private database rows.

## Verification

| Suite | Result |
|---|---|
| Isolated migration, Registrar create/update, student dashboard/PDF, MySQL import | 14 checks passed |
| Attendance and sanction workflow | 97 checks passed |
| CCS Excel, manual/bulk clearing, signatures and PDF workflow | 244 checks passed |
| Student import parsing/validation | Passed |
| 2,500-student import scale | Passed |
| Student dashboard history/completion consistency | Passed |
| PHP syntax for 61 changed/new PHP files | Passed |

The integration checks used synthetic accounts and disposable schemas. The focused migration test verifies that retained student fields and timestamps survive the drop, old section POST input is ignored, creation/import no longer require placeholders, and the clearance preview/PDF queries succeed with the column absent. Current production PHP has no remaining SQL or array access to the student section field.

The initial focused test exposed an existing hardcoded academic-year assumption in the MySQL importer test. That test now reads the active fixture term, and the complete focused rerun passed.
