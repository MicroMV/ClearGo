# CCS requirements completion and clearing

Date: 18 October 2026, Asia/Manila. This implements the user's replacement of evaluation screenshots with an official completion list, followed by manual individual or bulk approval.

## Using the feature

On the CCS Dean dashboard, open **Requirements completion list**:

1. Download the current Excel template. Students appear on separate **Year 1**, **Year 2**, **Year 3** and **Year 4** tabs, sorted by surname and first name within each year.
2. Find students by **Full Name** and **Course**, then select **Completed** or **Incomplete** from the **Requirements Completion** dropdown. Leave student numbers, names, course, school year and semester as provided. Blank completion cells are imported as **Incomplete**, including when the pending student's earlier status was Completed.
3. Import the `.xlsx` file. Completed verifies the applicable Dean requirement: Faculty Evaluation where applicable, or Application for Graduation for Year 4 in the second semester. Importing does not approve clearance.
4. Click **Clear** for one ready student, or **Clear all ready** for every ready student across all pages. Any positive batch size is allowed, including one student when many remain uncleared. There is no configured student-count ceiling on bulk clearing.

The template contains **Student Number**, **Full Name**, **Course**, **Requirements Completion**, **School Year** and **Semester**. The download is named `ccs-requirements-completion.xlsx`. Names and course identify roster entries; student numbers remain the import matching key. It contains no evaluation answers, comments or screenshots. The official evaluation system remains responsible for anonymous responses; ClearGo receives only completion status. Each year-level tab is included even when empty. Excel dropdown validation offers Completed/Incomplete, and the server independently validates imported values.

The export includes current enrolled CCS students with an uncleared Dean station, including fourth-year students in the second semester. The old requirement-applicability filter had excluded that group because fourth-year Faculty Evaluation applies only in the first semester. Year 4 now uses the same template in both semesters: Faculty Evaluation in the first semester and Application for Graduation in the second semester. Existing requirement applicability is preserved. A read-only local export check found Year 1: 149, Year 2: 141, Year 3: 95, Year 4: 54, with a dropdown on each sheet; two already-cleared fourth-year students remain excluded.

The importer reads all year-level worksheets together, skips empty roster tabs, and rejects duplicate student numbers across sheets. Previously downloaded Evaluation Completed headers, four- and five-column templates and their Yes/No values remain supported, with the new blank-as-Incomplete rule. Rows omitted from the workbook are not modified. Download a fresh template to get the Requirements Completion label, Course and the dropdowns. The format is the same for both semesters; each import must still match the active school year and semester.

Approved policy update: the user explicitly answered **Yes, allow Excel verification** for second-semester Year 4 Application for Graduation. Completed now verifies that requirement with the Dean ID and verification time. Incomplete/blank removes its verification while clearance is pending. The existing individual Verify Requirement action also remains available. Neither verification method clears the student; the Dean still chooses Clear or Clear all ready. Existing signed clearances are preserved.

Completion imports are restricted to the assigned CCS Dean, current active enrollments with clearance records, and the active PREPARING/OPEN semester. Imported completion verifies only applicable Faculty Evaluation or Application for Graduation requirements; it does not create out-of-semester requirements or verify unrelated offices. Duplicate numbers, unknown or other-college students, removed enrollments, invalid completion values, stale semester metadata and formulas are rejected. The entire file is validated before any changes are committed. Each recorded status has a Dean ID, timestamp and a CCS_REQUIREMENTS_COMPLETION_IMPORTED audit entry. Existing signed records are preserved, including when an uploaded completion cell is blank.

Imports accept `.xlsx` files up to 5 MB and 10,000 rows per file; larger rosters can use multiple imports. These upload limits do not cap the total number of ready students a bulk approval can process.

## Screenshot removal and existing rules

The student evaluation screenshot form and CCS Dean screenshot-review page are removed. The remaining Submitted Requirements link on the CCS Dean profile was also removed. The old upload, review-action, submitted-requirements page and image-handler files are deleted and return HTTP 404. The unused Dean screenshot stylesheet, screenshot-processing module and obsolete screenshot test suite were removed. Accounting, Library and Bookstore submission features remain intact.

Following the user's explicit request to remove this feature including its database data, migration `030_remove_evaluation_screenshot_storage.sql` was applied to local XAMPP. It drops `evaluation_submission_images` (5 rows), `evaluation_submissions` (5 rows), and `evaluation_verifications` (0 rows); removes obsolete screenshot notifications; and updates Faculty Evaluation instructions to describe the Excel workflow. Both private evaluation storage directories were removed, deleting all 8 old images, including images without a database reference. Historical audit logs remain as records of prior actions. Existing academic requirements and student approvals were preserved.

The unused legacy verification-table join in `approveDeanClearance` was removed before dropping the table. Core table row counts were compared before/after, and all 439 current Excel completion rows were compared by checksum and remained unchanged. See [removal evidence](REMOVAL.json). Original migrations and audit snapshots remain historical records; fresh installations must apply migration 030 after the earlier migrations. On another installation, delete only `storage/private/evaluation-proofs` and `storage/private/evaluation-submissions` after confirming their absolute locations inside the application storage; do not remove other offices' storage.

Application for Graduation can now be verified by the CCS Dean through the completion workbook or the existing individual Verify Requirement action. Clearance approval remains a separate action. Existing year/semester applicability, CBE Organization/YBC before Dean, other college requirements, and other offices' receipt/card/medical uploads are preserved. A completion list never bypasses the remaining applicable requirements.

Migration `029_ccs_evaluation_completion_list.sql` was applied locally. It adds the completion-metadata table without changing existing student records. Current pending evaluation requirements become ready when the Dean imports their completion list; old screenshot-verification flags alone are insufficient.

## Verification results

All tests that change workflow records used disposable schemas with copied table definitions/reference configuration and synthetic accounts. Temporary databases, local servers, test files and sessions were cleaned up.

| Executed check | Result |
|---|---|
| `tests/ccs_completion_workbook_test.php` | **97 passed**, including search, pagination, filters/report export, Ready/Undo Ready, remarks and authorization, plus the profile fix, absent legacy tables, removed endpoint 404 responses, both semesters' Year 4 verification, no automatic clearance, blank/Incomplete handling, audits, individual/bulk approval, names/course, dropdowns, cross-sheet validation and legacy templates |
| CCS Dean browser recheck | **81 passed** at 1440, 768, 390 and 320 pixels; see [recheck evidence](../ccs-dean-recheck/VERIFICATION.md) |
| `tests/clearance_report_workbook_test.php` | Passed after the year-level template update |
| Updated Phase 2 and Phase 3 suites | Passed inside the Excel suite's disposable database |
| `tests/library_book_return_policy_test.php` | **32 passed** |
| `tests/bookstore_ready_lock_test.php` | **30 passed** |
| `tests/registrar_cbe_initial_order_test.php` | **17 passed** |
| Shared bulk suite and student dashboard history/completion rendering | Passed |
| PHP syntax checks | All 18 changed/new PHP files passed |
| Test HTTP access guards | All 74 GET/POST requests denied |
| Removed screenshot pages on local XAMPP | All four returned HTTP 404 |
| Applied screenshot removal migration and storage cleanup | All three legacy tables and both old evaluation storage directories are absent; 8 images deleted; all 439 Excel records preserved |

The focused suite covers atomic rejection, role/college/enrollment boundaries, leading-zero student numbers, stale periods, formulas, Completed/Incomplete/blank values, legacy Yes/No, completion audit metadata, one-student bulk clearance, 35-student cross-page approval, manual clearing, historical approval preservation, retired screenshot access, and both Excel and individual graduation verification followed by separate clearance approval. It makes no school-load capacity claim beyond those executed batches.
