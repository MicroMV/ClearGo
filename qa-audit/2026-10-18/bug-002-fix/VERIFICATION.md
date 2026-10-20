# BUG-002 fix verification

Date: 18 October 2026, Asia/Manila. Scope: CBE Organization/YBC before Dean.

## Result

Registrar semester preparation and opening now initialize CBE with Organization **PENDING** and Dean **LOCKED**. Organization approval unlocks Dean. The existing CCS and CTE order remains unchanged.

The two Registrar paths use the same station initializer in `registrar/semester_functions.php`. Its insert preserves existing station decisions. Opening notices now direct Dean and Organization staff to students pending at their assigned office.

## Regression checks

Run from the project root:

```powershell
& C:\xamppl\php\php.exe tests/registrar_cbe_initial_order_test.php
```

All **17 checks passed**. The test executes application functions in a disposable MySQL schema using synthetic students and staff. It covers semester preparation/opening, fourth-year CBE, Organization approval unlocking Dean, preventing early Dean approval, preservation of prior approvals, opening notifications, the repair, completed and archived exclusions, and repeat execution. The temporary schema is removed afterward. Output is saved in [verification.log](verification.log).

PHP syntax checks passed for the changed application file, regression test and repair runner.

## Existing records

Migration `database/migrations/028_cbe_registrar_initial_station_order.sql` was tested before application. The protected CLI runner applied the repair in a transaction after saving [before-repair.json](before-repair.json).

| Check | Observed result |
|---|---:|
| Eligible untouched current CBE clearances | 1,059 |
| Changed station rows (Organization and Dean per clearance) | 2,118 |
| Remaining clearances matching the defect and repair criteria | 0 |

The repair requires an active enrolled student and an active period in PREPARING or OPEN, with Organization LOCKED and Dean PENDING. It excludes completed/archived clearances and any clearance with an office decision or approval/return metadata. Each repaired student's record received an audit entry with action `CBE_INITIAL_ORDER_REPAIRED`.

The exact preview and applied counts are saved in [repair-preview.json](repair-preview.json) and [repair-result.json](repair-result.json). These artifacts are inside the audit directory, which Apache denies to HTTP visitors. The saved station values allow a controlled rollback if needed; do not restore them over subsequent office decisions.

The original QA report remains historical evidence. This follow-up resolves BUG-002 only and does not change the other findings or certify deployment readiness.
