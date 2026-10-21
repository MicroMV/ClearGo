# ClearGo quality assurance report

**Attendance follow-up, 20 October 2026:** Both new scanner findings are fixed: compatibility scanning now uses supported camera settings and preserves the decoder's video aspect ratio; Retry closes the previous camera stream and scan loop before restarting. All 19 scanner browser checks and 9 lifecycle tests passed. These findings are separate from the original 16. See [fix verification](../2026-10-20/attendance-scanner-fix/VERIFICATION.md) and the [original attendance retest](../2026-10-20/attendance-retest/TEST_REPORT.md).

**Current status, 19 October 2026:** There are **no unresolved findings among the original 16 under the accepted requirements**. Thirteen findings are resolved by fixes, the approved prevention policy or removal of the affected feature (BUG-001–005, BUG-007–009 and BUG-012–016). BUG-006 and BUG-011 are withdrawn following the user's requirement clarifications. BUG-010 is accepted as the institution's current password policy; its documented security risk remains, and no code change was made for it. The original audit results and readiness assessment below remain historical and are not a new deployment certification.

**Post-audit updates, 18 October 2026:** BUG-002 has been fixed and verified. CBE now starts at Organization/YBC, followed by Dean. The repair corrected 1,059 untouched current CBE clearances; no matching stuck records remain. See [BUG-002 verification](bug-002-fix/VERIFICATION.md). BUG-001's Bookstore trigger is now prevented by the approved policy: Ready locks obligation/event fee changes, and signed students cannot receive new obligations. Student completion display also checks the office approvals. See [BUG-001 verification](bug-001-fix/VERIFICATION.md). The original findings, test counts and readiness assessment below describe the audit snapshot; these fixes do not resolve the remaining findings.

**BUG-003 post-audit update:** Browser access to the tests directory is now denied by Apache, and all 35 PHP tests have command-line-only guards. Verified 38 Apache path denials and 70 PHP-level GET/POST denials independently. See [BUG-003 verification](bug-003-fix/VERIFICATION.md). Deployment instructions now require excluding developer tests from production packages.

**BUG-004 post-audit update, superseded workflow:** The initial fix required separate screenshot review. The user subsequently replaced that policy with an official Excel completion list. Individual and bulk clearance now require recorded completion status and the applicable verified requirement; no screenshots are required. See [current CCS workflow verification](ccs-excel-completion/VERIFICATION.md). The [earlier verification](bug-004-fix/VERIFICATION.md) remains historical evidence.

**BUG-005 post-audit update:** Unreturned books now block Library individual, bulk, workbook and card-review clearance, including when a temporary exception exists. Staff and students can see the outstanding titles; Library staff can record each return before clearing the student. See [BUG-005 verification](bug-005-fix/VERIFICATION.md).

**BUG-008 post-audit update:** The application root `.htaccess` now denies HTTP access to `.git` directories and their contents. All 10 local Apache HEAD probes returned 403, including the original configuration URL, case and URL-encoding variants. Normal login/assets/status responses and the Dean login redirect still work. See [BUG-008 verification](bug-008-fix/VERIFICATION.md).

**BUG-009 post-audit update:** The application root `.htaccess` now blocks `.tmp` paths, with a second access denial inside the scratch directory. All four existing PHP scratch helpers have command-line-only guards before application code. Twelve local Apache HEAD probes were denied (403/404), and eight isolated GET/POST guard checks returned 403 before database access. See [BUG-009 verification](bug-009-fix/VERIFICATION.md).

**BUG-013 post-audit update:** Registrar staff creation and email updates now report duplicate emails with a controlled message. Other database failures use generic feedback, while technical details remain in server logs. All 35 isolated HTTP/account checks passed, including successful operations, rollback, authorization and CSRF. See [BUG-013 verification](bug-013-fix/VERIFICATION.md).

**BUG-014 post-audit update:** The shared CSRF validator now safely rejects non-string request values and malformed session tokens. Login also rejects array-shaped credentials without casting warnings. Web error display is disabled with logging enabled. The original Apache request now returns 303 with an empty body. All 47 focused checks and the 35 staff/97 CCS regression checks passed. See [BUG-014 verification](bug-014-fix/VERIFICATION.md).

**BUG-016 post-audit update:** Direct browser access to the entire storage tree is now denied by root and storage-directory `.htaccess` rules. All 13 Apache storage HEAD checks returned 403, and protected profile-picture upload/view checks passed for all 12 account roles. The fix was reconfirmed on 19 October 2026 and all generated test pictures were cleaned up. See [BUG-016 verification](bug-016-fix/VERIFICATION.md).

**BUG-006 scope correction:** Withdrawn following the user's clarification: SASO violation/disciplinary-case management is not a system requirement. SASO consolidates Organization and the Student Council, Library, Bookstore and Clinic signatures. Its dashboard description now reflects this scope. The original audit's 16 finding IDs and test counts remain historical. With BUG-006 and BUG-011 withdrawn, 14 findings remain in scope before accounting for the fixes above.

**BUG-011 workflow correction:** Withdrawn following the user's confirmation that CCS and CTE start with Dean, followed by Organization. The existing Dean-first behavior is correct for those colleges. CBE retains Organization/YBC before Dean. No application or database changes are required for this finding.

**Current CCS requirements completion workflow:** The CCS Dean downloads and imports an Excel template with names, courses, year-level tabs and a Completed/Incomplete dropdown; blank completion cells count as Incomplete. With the user's explicit approval, Completed verifies the applicable requirement, including Year 4 Faculty Evaluation in first semester or Application for Graduation in second semester. Importing does not clear students. The Dean then chooses Clear or Clear all ready across all pages, with no five-student minimum or configured student-count cap. The existing individual graduation verification action remains available. Student screenshot submission and Dean screenshot review/access are removed, including the leftover profile link. Migration 030 removed the three obsolete screenshot tables, and both private evaluation storage directories were deleted with all 8 images. All 439 current Excel completion records were preserved. BUG-007 is resolved for current application access, and BUG-015's obsolete retained images have been purged. See [usage and verification](ccs-excel-completion/VERIFICATION.md).

Audit date: **18 October 2026 — Asia/Manila**. Application snapshot: the user's existing working tree. Testing only: no application fixes, migration changes, workflow changes or UI changes were made during this audit.

## 1. Executive Summary

**Readiness: NOT READY for school deployment against the supplied requirements.** A positive CCS clearance can complete through all 11 offices, and the normal authentication, role separation, dashboard rendering, reporting, QR duplication handling, remarks and several import/upload paths work in isolated tests. All 32 existing regression suites passed after their environment assumptions were satisfied safely.

The original audit recorded **16 distinct findings**: 1 Critical, 9 High, 5 Medium and 1 Low. The most serious confirmed defects were an overall COMPLETED status that survives a reopened unpaid office, a deadlock in Registrar-created CBE clearances, and unauthenticated execution of a database-mutating test. Other findings concerned evaluation prerequisites/confidentiality and unreturned Library books. BUG-006 was subsequently withdrawn because SASO disciplinary cases are outside the confirmed requirements. BUG-011 was withdrawn because the user confirmed Dean-first ordering for CCS and CTE. The post-audit updates above record fixes and scope corrections separately from the original audit counts.

The report distinguishes executed tests, source observations, unimplemented features, and untested cases. It does **not** certify every discovered control or all real-device and school-load behavior.

## 2. Testing Coverage

| Measure | Actual audit count / scope |
|---|---|
| Implemented roles | 12 (11 approving offices plus Student); separate Administrator NOT IMPLEMENTED |
| Application source files discovered | 185 |
| PHP files discovered | 109 |
| UI/template/wrapper sources identified | 38; these include includes/shared views, not just independent pages |
| Role UI entry URLs probed | 31 plus the public login page |
| Named function declarations inventoried | 223 |
| Action declaration/branch occurrences inventoried | 160 |
| Technical feature entries | 383 function/action occurrences; not a claim of that many unique business features |
| Buttons/controls/interactive-container source occurrences | 633 including forms, inputs, links, summaries, dialogs and iframes |
| Source inventory matrix entries | 1125; separate from verification cases to avoid double-counting |
| Defined verification cases | 597 |
| PASS | 559 |
| FAIL | 18 |
| BLOCKED (final state) | 0 |
| NOT TESTED | 15 |
| NOT IMPLEMENTED | 5 |
| Distinct reported findings | 16 |

The 141-file PHP lint includes 109 application PHP files and 32 existing tests. Seventeen JavaScript files passed syntax checks. Nine existing suites ran safely in memory/temporary workbooks; 23 MySQL suites ran against a disposable database with the same 38 table definitions. Only reference configuration was copied; all users, students, semesters, uploads and mutations in the functional tests were synthetic. Original application source hashes were unchanged across 185 files.

Early MySQL profile/import failures were **harness setup issues**, not application defects: PHP's built-in server needed the original Apache denial rules emulated, and the import test's fixed academic year needed a matching synthetic semester. Retests passed. The first browser attempt timed out; a fresh isolated headless profile succeeded. An initial attendance redirect check was corrected after verifying its safe two-step login redirect. These histories remain in the evidence files.

Browser checks used desktop Edge with 320, 390, 768, 1024 and 1440-pixel student viewports. Staff dashboard overflow checks used 390 and 1440 pixels. Staff snapshots use real synthetic authorized HTTP responses with polling disabled for isolated layout checks; these are not full staff browser mutation tests.

## 3. Complete Testing Checklist

The complete verification table is in **[COMPLETE_CHECKLIST.md](COMPLETE_CHECKLIST.md)**, with machine-readable results in [all-test-results.csv](all-test-results.csv) and [all-test-results.json](all-test-results.json). Every discovered source item is separately listed by file/line in [coverage-matrix.csv](coverage-matrix.csv). That inventory explicitly leaves unproven per-control/per-function coverage as NOT TESTED; it does not promote a suite-level pass into a pass for every component.

| Test group | What actually ran | Final result |
|---|---|---|
| TC-001 / TC-002 | PHP and JavaScript parsing | PASS |
| 32 existing suites | Isolated SQL/SQLite, workbook, workflow, rendering, QR, profile, import, remarks and notification regressions | 32 PASS |
| SB-001–SB-199 | All 12 role logins, 31 authorized UI entry URLs, 132 cross-role dashboard denials, invalid CSRF and logout checks | 199 PASS |
| Browser checks | Student navigation/filter/modal/focus and responsive checks; staff document overflow; public-only worker caching, outage fallback and retry | 159 PASS |
| EX group | Search, special characters, pagination, XLSX downloads, password validation, account disablement and measured request batches | 64 PASS; 3 NOT TESTED |
| SC group | New specification/negative cases with HTTP, SQL and file evidence | 5 PASS; 12 FAIL |
| JY group | One eligible synthetic CCS student through actual form handlers, database inspection, refresh and re-login | 15 PASS |
| CC group | Five two-process simultaneous Library/Clinic approval trials | 5 PASS |
| Limit cases | Authenticity ground truth, real hardware, school pilot and other unexecuted requirements | Explicit NOT TESTED / NOT IMPLEMENTED |

Statuses mean: PASS = the stated assertion was executed and satisfied; FAIL = its assertion was executed and violated; BLOCKED = a prerequisite prevented execution; NOT TESTED = no reliable execution/acceptance evidence for that case; NOT IMPLEMENTED = the expected capability was not found. A FAIL is not automatically a separate bug: multiple cases can demonstrate one defect.

## 4. Complete Bug List

All findings below have safe evidence. A source-only inference or intentionally unexecuted dangerous step is labelled explicitly. Reproduction instructions refer to the disposable fixture unless a safe HEAD/invalid-form request is specifically identified.

### Critical

#### BUG-001 — Completed clearance stays completed after an office reopens it

| Field | Finding |
|---|---|
| Severity | Critical |
| Affected role | Student, Bookstore, Registrar |
| Location | clearance/office_action.php:111; student/dashboard_view.php:15 |
| Problem | Adding an unpaid Bookstore obligation returns the Bookstore signature but leaves the overall clearance marked COMPLETED. |
| Simple explanation | A student can still look fully cleared after the school records a new unpaid obligation. |
| How to reproduce | In the isolated fixture, complete every office and set the clearance to COMPLETED. Sign in as Bookstore. Submit add_bookstore_obligation for that student with an amount of 100. Inspect both the clearance and Bookstore station rows, then reopen the student dashboard. |
| Expected result | Either reject changes to a finalized clearance with a clear policy, or reopen the overall record and invalidate affected downstream approvals consistently. |
| Actual result | The real POST returned 303. Bookstore became RETURNED while clearances.status remained COMPLETED with completed_at populated. |
| Evidence | SC-009 in scenario-tests.json; source shows the student completion flag trusts overall COMPLETED even when office counts disagree. |
| Root cause | The new-obligation handler changes only the office record. There is no shared downstream revocation/overall-status reconciliation. |
| Suggested fix | Define one transactional revocation rule, apply it to reopened obligations and downstream signatures, and derive completion from consistent authoritative records. |
| Impact | Incorrect final clearance and contradictory student/office records. |
| Verification status | Originally reproduced through the actual HTTP handler. Fixed after the audit for the Bookstore scenario by rejecting obligation changes after Ready or student approval and checking office approvals before displaying completion. Verified with 30 isolated checks, authenticated HTTP requests and student dashboard rendering tests. See [follow-up verification](bug-001-fix/VERIFICATION.md). |

### High

#### BUG-002 — Registrar-created CBE clearances are stuck between Organization and Dean

| Field | Finding |
|---|---|
| Severity | High |
| Affected role | CBE Student, Organization/YBC, Dean, Registrar |
| Location | registrar/semester_functions.php:52 and :178; clearance/clearance_functions.php:270; organization/clearance_functions.php:112 |
| Problem | The normal Registrar initializer makes Dean pending and Organization locked for CBE, although CBE Dean approval requires Organization/YBC approval first. |
| Simple explanation | Each office waits for the other, so a newly enrolled CBE student cannot get started. |
| How to reproduce | Create a synthetic CBE enrollment and call prepareSemesterClearanceRecords(), as the Registrar flow does. Read the station statuses. Attempt Organization approval and then Dean approval with assigned synthetic staff. |
| Expected result | Organization PENDING, Dean LOCKED, and Organization/YBC can begin the CBE sequence. |
| Actual result | Organization was LOCKED and Dean PENDING. Organization reported that Dean must approve first; Dean reported that CBE Organization/YBC approval is required first. |
| Evidence | SC-001. Existing CBE suites passed because they use startStudentClearance(), a different initializer that handles CBE specially. |
| Root cause | Registrar initialization and semester opening hard-code DEAN as the first station. The legacy student initializer uses a college-specific first station. |
| Suggested fix | Use one authoritative college-specific initializer for preparation, semester opening, and any legacy entry path. Test newly generated CBE records, not just migrated records. |
| Impact | Blocks the real CBE clearance journey. |
| Verification status | Originally reproduced with actual application functions and saved station rows. Fixed after the audit: 17 regression checks passed, and 1,059 untouched current CBE clearances were repaired. See [follow-up verification](bug-002-fix/VERIFICATION.md). |

#### BUG-003 — An unauthenticated test URL changes office readiness data

| Field | Finding |
|---|---|
| Severity | High |
| Affected role | Unauthenticated visitor, office staff, Registrar |
| Location | tests/readiness_test.php:8, :46, :53; tests/ directory has no HTTP denial rule |
| Problem | A database integration test is executable through the web without login and performs real readiness mutations. |
| Simple explanation | Someone who has not signed in can run a developer test against the school's data. |
| How to reproduce | Use only the isolated server: set its semester to PREPARING and place synthetic notes on NOT_READY offices. Request /tests/readiness_test.php without a cookie. Compare the notes afterward. Do not repeat this against the school database. |
| Expected result | HTTP 403 or 404 with no database changes. |
| Actual result | HTTP 200 returned a passed-test message; a Student Council readiness note became NULL. The live Apache also exposes /tests/ and executes the harmless navigation regression without login. |
| Evidence | SC-008; TC-115 and TC-116. Actual mutation was demonstrated only on synthetic records. |
| Root cause | Several integration tests lack a CLI-only guard, and the deployed tests directory is web-accessible. |
| Suggested fix | Exclude tests from the served document root or deny HTTP access to the entire directory; also enforce CLI-only execution in database-mutating scripts. |
| Impact | Unauthorized state changes, lost notes, test data creation, and unexpected processing load. |
| Verification status | Originally reproduced unauthenticated mutation in the isolated deployment. Fixed after the audit: Apache denied all 38 tested directory/file paths, and PHP guards independently denied 70 GET/POST requests across all 35 test scripts. Representative command-line regressions remain operational. See [follow-up verification](bug-003-fix/VERIFICATION.md). |

#### BUG-004 — Single Dean approval verifies an evaluation that was never submitted

| Field | Finding |
|---|---|
| Severity | High |
| Affected role | CCS Student and Dean |
| Location | clearance/clearance_functions.php:298; dean/action.php:87 |
| Problem | Individual approval automatically marks CCS faculty-evaluation requirements VERIFIED instead of requiring an independently verified result. |
| Simple explanation | The approval button can turn missing evaluation evidence into a verified requirement. |
| How to reproduce | Create a synthetic CCS clearance with NOT_STARTED evaluation requirements and no evaluation submission. Sign in as its Dean or invoke approveDeanClearance() with that assigned synthetic Dean. Inspect the Dean and requirement records. |
| Expected result | Under the supplied specification, approval is rejected until evaluation verification is complete. |
| Actual result | With zero proof submissions, Dean approval succeeded and the requirement was marked verified during approval. |
| Evidence | SC-003; individual and ready-only bulk paths use different prerequisite behavior. |
| Root cause | The individual approval path treats the Dean's click as verification; the readyOnly checks do not apply to the ordinary individual path. |
| Suggested fix | Require a trusted verification result before signing, or explicitly approve and document a separate manual-attestation policy. Keep individual and batch eligibility consistent. |
| Impact | Evaluation requirements can be satisfied without the evidence required by the supplied specification. |
| Verification status | Originally reproduced with actual application functions and saved MySQL state. Current policy requires an imported official completion-list status before individual/bulk clearance. Clearance does not auto-confirm missing completion. The initial screenshot-based fix was superseded; see [current verification](ccs-excel-completion/VERIFICATION.md). |

#### BUG-005 — Library can approve a student who still has a borrowed book

| Field | Finding |
|---|---|
| Severity | High |
| Affected role | Student and Library |
| Location | clearance/office_workflow.php:58 and :279; library dashboard uses the shared eligibility rules |
| Problem | Library eligibility checks the card status but does not check outstanding borrowed_books rows. |
| Simple explanation | A verified library card can be enough to clear a student even while a book is still out. |
| How to reproduce | In the isolated database, give a student a VERIFIED library card and a borrowed_books record with status BORROWED. Make Library PENDING. Call the actual eligibility and approval functions. |
| Expected result | The outstanding book prevents approval and is explained to staff/student. |
| Actual result | Eligibility was true and Library became APPROVED while the book remained BORROWED. |
| Evidence | SC-005. The fixture explicitly inserts a verified card; an earlier fixture that omitted that row was corrected before final results were recorded. |
| Root cause | Borrowed-book obligations are absent from Library list, eligibility, and generic approval checks. |
| Suggested fix | Include unreturned books in the authoritative Library eligibility rule and expose the blocker in the relevant interface. |
| Impact | Unreturned school property may not prevent clearance. |
| Verification status | Originally reproduced with saved card, book, and station rows. Fixed after the audit: 32 isolated Library checks passed across eligibility, individual/bulk/workbook/card-review approvals, staff/student displays and recorded book returns. Shared bulk, student dashboard and Dean workflow regressions also passed. See [follow-up verification](bug-005-fix/VERIFICATION.md). |

#### BUG-006 — Withdrawn: SASO violations are outside the requirements

| Field | Disposition |
|---|---|
| Status | WITHDRAWN / OUT OF SCOPE; no bug fix required |
| Confirmed requirement | SASO reviews the required office signatures; it does not manage or block on SASO disciplinary cases. |
| User decision | Remove SASO violations because they are not part of the system requirements. |
| Change | Removed the dashboard wording about conduct requirements and outstanding cases. No SASO violation feature or blocker is required. |
| Historical evidence | SC-006 remains an observation of the original audit fixture, rather than a failed requirement under the clarified scope. |

#### BUG-007 — Dean can retrieve confidential evaluation screenshots

| Field | Finding |
|---|---|
| Severity | High |
| Affected role | Student and CCS Dean |
| Location | dean/evaluation_image.php:6 and :19; dean/submitted_requirements.php:85 |
| Problem | The implementation gives the CCS Dean the original submitted image rather than only a verification result. |
| Simple explanation | Changing a submission's label to an anonymous code does not hide names or private content inside its screenshot. |
| How to reproduce | Upload a synthetic evaluation bitmap through the actual student endpoint. Sign in as the assigned CCS Dean. Request its /dean/evaluation_image.php?id=... URL. |
| Expected result | The supplied privacy requirement says the Dean receives only the necessary verified/unverified result and cannot retrieve confidential screenshots. |
| Actual result | The Dean received HTTP 200 and the original PNG bytes. The submissions page embeds and links these images. |
| Evidence | SC-012. Only a generated nonpersonal image was used. A Library role was denied access in EX-062. |
| Root cause | The current product deliberately uses anonymous manual image review by the Dean, which conflicts with the requested confidentiality model. |
| Suggested fix | Resolve the privacy/workflow specification before deployment. Separate confidential evidence review from Dean signing, expose only the required result, and define retention/access rules. |
| Impact | Potential exposure of identity and confidential evaluation content to a role the supplied specification excludes. |
| Verification status | Originally reproduced through the authenticated image endpoint; no real student's image was accessed. Resolved by removing evaluation screenshots: the student upload, Dean review and image routes are deleted and return HTTP 404. Old images and screenshot tables were purged; the new flow records completion metadata only. See [current verification](ccs-excel-completion/VERIFICATION.md). |

#### BUG-008 — Apache serves Git configuration from the project

| Field | Finding |
|---|---|
| Severity | High |
| Affected role | Unauthenticated visitor |
| Location | /.git/config; web document-root access rules |
| Problem | The Git configuration path is publicly readable through Apache. |
| Simple explanation | Internal repository files are being served as website files. |
| How to reproduce | Send HEAD /ClearGo/.git/config to the local Apache deployment without a cookie. |
| Expected result | HTTP 403 or 404. |
| Actual result | HTTP 200. |
| Evidence | TC-113. Only headers were requested; configuration contents and credentials were not retrieved. |
| Root cause | No effective web-server denial covers the .git directory in this deployment. |
| Suggested fix | Keep repository metadata outside the served tree or explicitly deny access to version-control directories. |
| Impact | Exposes repository metadata and may allow additional repository/history disclosure. No secret disclosure was claimed or attempted. |
| Verification status | Originally reproduced with a non-content HEAD request on local Apache. Fixed by the application root `.htaccess` Git-directory denial. Ten HEAD probes now return 403, including the originally exposed configuration URL; normal public resources and the Dean authentication redirect remain operational. See [verification](bug-008-fix/VERIFICATION.md). |

#### BUG-009 — Temporary developer scripts are inside a web-accessible directory

| Field | Finding |
|---|---|
| Severity | High |
| Affected role | Unauthenticated visitor and developers |
| Location | /.tmp/; .tmp/apply-office-remarks.php:2 |
| Problem | Apache exposes the developer scratch directory, which contains scripts that connect to the application database and can apply migrations or run checks. |
| Simple explanation | Files intended for development can be reached as website URLs. |
| How to reproduce | Send HEAD /ClearGo/.tmp/. Review the local scratch scripts without requesting their mutating URLs. |
| Expected result | The scratch directory is unavailable through HTTP. |
| Actual result | The directory returned HTTP 200. Source inspection found an unguarded migration helper there. |
| Evidence | TC-114; local source inspection of .tmp/apply-office-remarks.php. No migration helper was invoked through HTTP. |
| Root cause | The Git ignore rule for .tmp does not control Apache access. |
| Suggested fix | Move scratch files outside the document root or deny the directory at the server level. Remove development helpers from deployment packages. |
| Impact | Increases the attack surface and can expose operations that were never intended for normal users. |
| Verification status | Originally reproduced by directory exposure; the migration helper was not executed through Apache. Fixed with root and directory HTTP denial plus CLI-only guards on all four PHP scratch helpers. Twelve Apache HEAD probes were blocked and eight isolated GET/POST guard checks returned 403. See [verification](bug-009-fix/VERIFICATION.md). |

#### BUG-010 — Accepted policy: new and reset accounts share a predictable initial password

| Field | Finding |
|---|---|
| Severity | High |
| Affected role | All accounts and Registrar |
| Location | registrar/student_import_functions.php:10 and :626; registrar/staff_action.php:44 and :87; registrar/student_action.php:57 and :119 |
| Problem | Import, account creation, and password reset use one institutional default without a required first-login password change. |
| Simple explanation | Knowing one new account's initial password reveals the default used for other new accounts. |
| How to reproduce | Run the MySQL student-import fixture in the isolated database. Verify the created account's password hash against the institutional initial value. Inspect the normal login destination and account-creation/reset paths. |
| Expected result | A new/reset account receives a unique temporary secret or equivalent activation process and must establish its own credential. |
| Actual result | The existing MySQL import test confirms the shared default is accepted. The login path redirects directly to the role dashboard, with no mandatory-change state. |
| Evidence | Final passing student_import_mysql_test.php and student_import_scale_test.php; source paths above. No existing real account was tested with the default. |
| Root cause | A shared constant is used for account passwords; the required-change policy was removed in migration 005. |
| Suggested fix | Use unique temporary credentials/activation and enforce first-login replacement; define safe reset and credential-delivery behavior. |
| Impact | Accounts that retain the initial password are predictable. This is a verified configuration weakness, not a claim that a real account was compromised. |
| Verification status | Accepted by the user as the institution's current policy on 19 October 2026. No code change was made; the documented risk remains. |

### Medium

#### BUG-011 — Withdrawn: Dean-first ordering is correct for CCS and CTE

| Field | Finding |
|---|---|
| Severity | Withdrawn (originally Medium) |
| Affected role | CCS/CTE students, Organization, Dean |
| Location | database/migrations/001_phase1_clearance_workflow.sql:11; clearance/clearance_functions.php:95; registrar/semester_functions.php:52 |
| Problem | The original audit assumed Organization should precede Dean for CCS and CTE. The user confirmed that Dean first is correct. |
| Simple explanation | The implemented office order matches the confirmed school workflow. |
| How to reproduce | Initialize a synthetic CCS enrollment through the Registrar function and read its saved station rows. Compare the configured migration order and the supplied sequence. |
| Expected result | CCS and CTE: Dean before Organization. CBE: Organization/YBC before Dean. |
| Actual result | Dean is PENDING and Organization LOCKED. The positive CCS journey completes in Dean-first order. |
| Evidence | SC-002; JY-002 through JY-015; the initial migration and current UI agree with the implemented order. |
| Root cause | The audit interpreted the supplied sequence as applying equally to all colleges. The user clarified the per-college order. |
| Suggested fix | No application fix is needed. Retain the configured order and record the corrected policy. |
| Impact | No defect under the confirmed workflow. |
| Verification status | Withdrawn after the user's explicit Dean-first confirmation. The original CCS journey already completed in that order; no code or database changes were made for this correction. |

#### BUG-012 — Student evaluation uploads are accepted before clearance opens

| Field | Finding |
|---|---|
| Severity | Medium |
| Affected role | Student and Dean |
| Location | clearance/evaluation_functions.php:7 and :164; student/evaluation_action.php:20 |
| Problem | The evaluation submission backend checks active semester and Dean state but does not require clearance_status OPEN. |
| Simple explanation | Hiding the upload form before opening does not stop a crafted or previously opened form from submitting. |
| How to reproduce | In the isolated fixture, keep a student's Dean station PENDING and set the semester to PREPARING. Sign in as that student and POST a generated valid-format PNG with the student's valid CSRF token. |
| Expected result | The server rejects the student submission until clearance opens. |
| Actual result | The endpoint returned 303 and saved a SUBMITTED evaluation record while the semester was PREPARING. |
| Evidence | SC-011. SC-004 also confirms staff approvals during PREPARING, but the current staff interface explicitly permits that; staff preparation policy is documented separately rather than mislabeled as an authorization exploit. |
| Root cause | studentEvaluationContext() has no OPEN-period or current-enrollment predicate, unlike several other student upload handlers. |
| Suggested fix | Apply a shared student submission gate for semester state, enrollment, and station availability at the backend. |
| Impact | Students can submit outside the period shown as available by the dashboard. |
| Verification status | Originally reproduced through the multipart upload endpoint and verified in MySQL. Resolved by removing the student evaluation screenshot feature and its upload endpoint, which now returns HTTP 404. Dean Excel imports during PREPARING remain intentionally permitted for staff preparation. See [current verification](ccs-excel-completion/VERIFICATION.md). |

#### BUG-013 — Duplicate staff creation displays a raw database error

| Field | Finding |
|---|---|
| Severity | Medium |
| Affected role | Registrar |
| Location | registrar/staff_action.php:118; similar RuntimeException message branches elsewhere need review |
| Problem | A PDO exception is treated as a user-safe runtime validation error and displayed through a flash message. |
| Simple explanation | A duplicate email shows database language instead of explaining that the staff account already exists. |
| How to reproduce | Sign in as the synthetic Registrar. Submit creation of a staff account using an existing synthetic Library email. Follow the redirect to Staff Accounts. |
| Expected result | A message such as 'That school email already has an account.' Technical details go only to logs. |
| Actual result | The returned staff page contained SQLSTATE/duplicate-entry details. |
| Evidence | SC-010. PDOException inherits from RuntimeException, so the current instanceof check is insufficient. |
| Root cause | The catch branch forwards all RuntimeException messages, including database exceptions. |
| Suggested fix | Handle expected domain-validation errors separately from PDOException and other internal failures. Map duplicate-key conflicts to a controlled message. |
| Impact | Confusing feedback and disclosure of internal database details. |
| Verification status | Originally reproduced using an actual duplicate synthetic account POST. Fixed in Registrar staff creation and email updates: duplicate conflicts now show a friendly message and other PDO errors use generic feedback. All 35 focused checks passed in a disposable database; no real accounts were changed. See [verification](bug-013-fix/VERIFICATION.md). |

#### BUG-014 — An array-shaped CSRF token triggers a raw PHP fatal error with HTTP 200

| Field | Finding |
|---|---|
| Severity | Medium |
| Affected role | Unauthenticated login user |
| Location | auth/login_process.php:12; auth/auth_check.php:198; PHP web error-display configuration |
| Problem | The request passes an unvalidated array into a ?string parameter before the handler can reject it safely. |
| Simple explanation | An unusual form value makes the login page show a stack trace and internal file paths. |
| How to reproduce | POST csrf_token[]=invalid to /ClearGo/auth/login_process.php without credentials. No database mutation is involved. |
| Expected result | A controlled invalid-request response or friendly redirect, without warnings, paths, or stack traces. |
| Actual result | HTTP 200 text/html contained TypeError, Fatal error, auth_check.php:198, and a stack trace. |
| Evidence | TC-107; malformed-csrf-evidence.json contains the observed response. |
| Root cause | Type validation is missing at the request boundary, and the local web PHP configuration displays internal errors. |
| Suggested fix | Validate token shape before calling verification, handle malformed input uniformly, return an appropriate status, and log internal errors instead of displaying them. |
| Impact | Internal implementation disclosure and misleading successful HTTP status on a failed request. |
| Verification status | Originally reproduced on local Apache using a safe invalid request. Fixed: the original array-shaped-token POST now returns a controlled 303 redirect with an empty body. Forty-seven focused checks and the staff/CCS login and workflow regressions passed. See [verification](bug-014-fix/VERIFICATION.md). |

#### BUG-015 — Evaluation resubmission leaves the old private image on disk

| Field | Finding |
|---|---|
| Severity | Medium |
| Affected role | Student and system operator |
| Location | clearance/evaluation_functions.php:209 and :228 |
| Problem | Resubmission deletes image references from the database but removes stored files only on the exception path, not when replacement succeeds. |
| Simple explanation | Old screenshots remain in storage even though the system no longer lists them. |
| How to reproduce | Upload one generated synthetic evaluation image, save its stored filename, then submit a replacement. Check whether the original database reference and file still exist. |
| Expected result | Superseded evidence is removed according to an explicit retention policy, or retained with an auditable reference. |
| Actual result | The old image reference count became zero while its original file remained on disk. |
| Evidence | SC-013; generated images only. |
| Current workflow | Evaluation screenshot uploads and resubmissions are removed. The user subsequently authorized full deletion: all 8 stored evaluation images, both old evaluation storage directories and the three obsolete screenshot tables were removed. The current Excel completion records were preserved. |
| Root cause | Successful replacement does not collect and unlink old stored filenames after the transaction commits. |
| Suggested fix | Track replacement files and clean them after successful commit, with rollback-safe handling and a defined retention policy. |
| Impact | Unbounded storage growth and unmanaged retention of confidential evidence. |
| Verification status | Originally reproduced through two multipart submissions and a filesystem/database comparison. Resolved by removing the screenshot feature and purging its retained images. See [removal evidence](ccs-excel-completion/REMOVAL.json) and [current verification](ccs-excel-completion/VERIFICATION.md). |

### Low

#### BUG-016 — The storage directory itself allows public directory access

| Field | Finding |
|---|---|
| Severity | Low |
| Affected role | Unauthenticated visitor |
| Location | /storage/ Apache directory handling |
| Problem | The storage root returns a public directory response even though its private and sessions subdirectories are denied. |
| Simple explanation | Internal runtime-folder structure is visible to visitors. |
| How to reproduce | Send HEAD /ClearGo/storage/, then compare HEAD responses for /storage/private/ and /storage/sessions/. |
| Expected result | The runtime storage root is also denied or has directory indexing disabled. |
| Actual result | Storage root returned 200; private and sessions returned 403. |
| Evidence | TC-110 through TC-112. No private session or uploaded file body was requested. |
| Root cause | Access denial is applied to subdirectories but not the storage root. |
| Suggested fix | Disable directory listing and deny direct access to the runtime storage tree while keeping protected image handlers available. |
| Impact | Minor internal-structure disclosure. This finding does not claim private files are publicly downloadable. |
| Verification status | Originally reproduced with HEAD requests on Apache; listing contents were not retrieved. Fixed with direct-access denial for the entire storage tree. All 13 storage URL checks returned 403; authorized profile-picture uploads and viewing passed for all 12 account roles. Reconfirmed on 19 October 2026. See [verification](bug-016-fix/VERIFICATION.md). |

## 5. Office-by-Office Findings

| Role / office | Executed evidence | Finding / limit |
|---|---|---|
| Student | Login/logout, active/history/empty/completed views, navigation, filters, requirements/scanner/password dialogs, proof upload, password validation, profile ownership and completed re-login | Positive path works; BUG-001, BUG-007, BUG-012 and BUG-015 affect correctness/privacy. Physical cameras and genuine evidence remain untested. |
| Organization / CCS, CBE, CTE | Role separation, assigned-college workflow suites, summaries, filters, XLSX, QR event tests, individual HTTP approval and empty selection | CCS configured Dean-first path completes. Newly initialized CBE flow deadlocks (BUG-002). Exclusion-selection batch UI was not found. |
| Dean | Assigned-college access, group workflow, bulk eligibility/minimum batch, individual signing, evaluation image access, exports | BUG-002 / 004 / 007 / 011. Manual image review and automatic individual attestation conflict with the supplied verification/privacy model. |
| SASO | Aggregator eligibility, child unlocking, actual HTTP signature, role separation and summaries | Positive child aggregation works. Disciplinary cases are outside the confirmed requirements; BUG-006 is withdrawn. |
| Student Council | Dashboard/attendance/QR entry routes, required attendance eligibility, actual approval, workbook/report and shared guards | Covered fixtures passed. Physical attendance cannot be established from a QR token alone; genuine presence was not measured. |
| Library | Workbook import/readback, card/profile-related guards, actual signature and outstanding-book negative case | Verified-card positive path works; outstanding books are ignored (BUG-005). |
| Bookstore | Fees/submissions pages, event workbook/obligation suites, reports, actual signature and new obligation after completion | Normal fixtures pass; reopening an obligation leaves stale overall completion (BUG-001). |
| Clinic | Schedules page, medical/workbook suite, shared eligibility/signature, simultaneous child approval | Covered fixtures passed; real clinical attendance and every schedule-capacity race were not tested. |
| Facilities | Borrowed-items page, return-query and modal cases, audience/department rules, actual signature and reports | Covered missing-return/second-semester toga eligibility fixtures passed. Physical returns were not verified. |
| Computer Laboratory | Violation/history pages, modal states and escaping, eligibility, actual signature and reports | Included as the implemented extra stage between Facilities and Accounting; covered fixtures passed. |
| Accounting | Balance/portal eligibility fixtures, screenshot submissions page, manual-review flow and actual signature | Covered fixtures passed. Manual settlement/exception actions exist; this audit does not establish real financial truth or receipt authenticity. |
| Registrar | Student/staff/admin pages, semester/readiness functions, import preview/commit, reports/audit, disabled accounts, final signature and persistence | BUG-001 / 002 / 010 / 013. Final unsigned-office guard passes; authoritative initializer is inconsistent for CBE. |
| Administrator | Role constants, migrations and route discovery | NOT IMPLEMENTED as a separate role by design; management resides with Registrar. Do not silently add a new admin role. |

## 6. Full Workflow Results

The **actual configured CCS/CTE primary sequence** is Dean → Organization → SASO child checks (Student Council, Library, Bookstore, Clinic) → SASO → Facilities → Computer Laboratory → Accounting → Registrar → COMPLETED. The user confirmed Dean before Organization for CCS and CTE, so BUG-011 is withdrawn. CBE uses Organization/YBC before Dean.

CBE's intended special path is Organization/YBC → Dean → SASO child checks → SASO → Facilities → Computer Laboratory → Accounting → Registrar. The legacy startStudentClearance() path handles this, but the normal Registrar preparation/opening initializer starts everyone at Dean, producing BUG-002. The audit tested that exact Dean-pending/Organization-locked condition directly.

The positive CCS journey used a newly created synthetic student with independently seeded satisfied prerequisites. It submitted every approval through the real HTTP form handlers, inspected approved_by and approved_at, refreshed the student's page after every step, checked all eleven saved signatures, and confirmed COMPLETED after a fresh login. It proves that configured positive flow, **not** that a synthetic screenshot establishes genuine evaluation completion.

Locked/prerequisite cases, closed-period rejection, no duplicate student/semester records, final unsigned-office denial, required attendance/financial/medical/return eligibility fixtures and five simultaneous child-approval trials were exercised. SASO unlocked correctly in all five concurrent Library/Clinic trials. Revocation consistency fails after final completion (BUG-001).

The staff UI explicitly supports signing complete students during PREPARING and describes this as semester preparation; SC-004 reproduced that behavior. It should be reconciled with the school's opening policy, rather than reported as a hidden role bypass. Student evaluation POSTs during PREPARING remain an inconsistent backend gate (BUG-012).

## 7. UI/UX Findings

Student navigation, filters, modal Escape behavior, focus return, inert backgrounds, completion/history/empty states and document overflow passed in the tested viewports. The current light clearance-card palette fits the surrounding portal in the captured synthetic preview. Representative screenshots: [student mobile](student-390.png), [student desktop](student-1440.png), [Dean mobile](dean-390.png), [Registrar mobile](registrar-390.png).

Important usability failures are the CBE waiting loop, stale completed state, raw SQL duplicate-account message and raw PHP malformed-request output. Recommended messages include “That school email already has an account” and “We couldn't process that request. Please try again.” The present shared outage screen uses simple language, and its cached fallback/retry passed. Nothing was replaced during the audit.

The system does not currently provide a separate Administrator, generic Approve All Except Selected flow, or login password-visibility toggle. Those absences are recorded without labelling nonexistent controls as broken. Existing per-office batch rules differ; CCS Dean's minimum batch and final 1–4 student exception were exercised. Per-control source inventory remains available for follow-up testing; this is not a claim that every radio, input and conditional button was clicked on a physical phone.

## 8. Security Findings

Verified protections: all twelve ordinary role sessions authenticated correctly; all 132 wrong-role dashboard attempts were denied; invalid-token mutation attempts were rejected; logout and disabled-account invalidation passed; private upload/session directories were denied in Apache; profile ownership and cross-college remarks checks passed; a Library account could not read a Dean image. QR tests confirmed another college's student was denied, duplicate scans saved one row, and expired tokens were rejected.

Verified failures: unauthenticated test execution can mutate readiness notes; Git and scratch paths are exposed; shared initial passwords are predictable; malformed CSRF input reveals a stack trace; duplicate staff creation reveals SQL details; and the specified evaluation confidentiality boundary is not met. Git contents, real sessions, real student images and real account passwords were not fetched or attacked. The bulk-dashboard test helper's temporary session was destroyed; a persistent staff-login bypass was **not** reproduced and is not claimed.

A second existing student session remained usable after a password change in the synthetic observation. A school session-revocation/idle policy was not supplied, so compliance is untested; define it before accepting that behavior. This audit does not claim exhaustive SQL injection/XSS assurance: covered special-character searches, escaping fixtures and prepared-query paths passed, while unexercised source branches remain in the matrix.

Evaluation handling currently stores images for anonymous **manual CCS Dean review**. The old OPENAI_VISION metadata function is not proof of a working AI authenticity service. A valid-format unrelated image remained SUBMITTED, not automatically VERIFIED; genuine authenticity, tamper detection, identity matching and false-positive rates were not established. No student information was sent to an outside service.

PWA caching contains only the public fallback page and logo in the observed cache. Protected student records were not cached in that test. The manifest/install feature is NOT IMPLEMENTED; old-client upgrades and physical-device installation remain unverified.

## 9. Performance and Efficiency Findings

Measured local results (synthetic roster, one staff account, one local PHP server process):

| Request batch | Concurrency | Wall time | Median response | 95th percentile | Maximum | Result |
|---|---|---|---|---|---|---|
| 1 | 1 | 48 ms | 47.73 ms | 47.73 ms | 47.73 ms | All HTTP 200 |
| 30 | 4 | 967 ms | 120.14 ms | 178.88 ms | 179.2 ms | All HTTP 200 |
| 100 | 8 | 3676 ms | 267.71 ms | 453.35 ms | 474.02 ms | All HTTP 200 |

The separate SQLite importer test processed 2,500 synthetic students in the time printed in [student_import_scale_test.php.log](student_import_scale_test.php.log) (0.21 seconds in this run). That is an in-memory import measurement, not a full MySQL import benchmark or a school deployment forecast. The 30/100 request batches are **not** 30/100 concurrently active students. Five two-process approval trials passed, but 500/1,000 active-user workloads and all concurrency combinations were not tested. Response distributions are in [performance.json](performance.json).

The actual database health probe returned HTTP 200 with available=true during the audit. Earlier conversation-time startup failures are not counted as an ongoing application outage in the final results. This report did not repair or restart MySQL.

### Comparison with paper clearance

No school pilot or measured paper baseline was available. The paper column below describes ordinary assumed paper-workflow characteristics, not observed measurements at The Lewis College.

| Criterion | Paper process: assumption / no measurement | ClearGo: observed or limited finding |
|---|---|---|
| Completion time | Requires collecting signatures; actual elapsed time unknown | No defensible percentage saved. Configured positive electronic journey passed; CBE deadlock prevents a universal improvement claim. |
| Travel between offices | Physical signature collection generally requires visits | Online status/proof submission can reduce signature-only visits; books, incidents, payments and medical appointments may still require physical action. |
| Staff workload | Individual paper checks/signatures | Batch ready-roster processing, search and Excel tools work in covered fixtures; manual screenshot review and duplicate data entry can add work. |
| Record accuracy | Handwriting/lost forms can cause errors | Structured statuses, timestamps and audit logs exist; the original BUG-001, BUG-004 and BUG-005 findings concerned correctness. See the post-audit fix updates above. |
| Lost/damaged records | Paper can be physically lost or damaged | Electronic records avoid paper damage but require backups/recovery. Orphan proof files show retention needs work. |
| Pending requirements | Students typically ask offices or read marks | Portal shows office states, remarks and next action in tested views; CBE?s contradictory prerequisites are confusing. |
| Bulk processing | Typically manual | Ready-roster selection and batch eligibility fixtures passed; generic exclusion-selection UI was not found. |
| Status transparency | Depends on the sheet and staff explanations | Student refresh reflects normal approvals, but stale overall completion can contradict obligations. |
| Availability | Offices have service hours | Web access depends on server/database availability. Public cached outage feedback works; records are not usable offline. |
| Cost/resources | Paper/printing and staff time, unmeasured | Hosting, maintenance, backup and device costs were not costed. No savings figure is claimed. |
| Privacy/accountability | Physical custody and handwritten signatures | Ordinary role restrictions/audits work in covered paths, but evaluation privacy, exposed developer paths and default credentials need attention. |

ClearGo has demonstrated tools that can improve tracking and batch processing. It has not yet demonstrated that it can reliably replace the full paper process for all colleges or that it saves a measured amount of time.

## 10. Recommended Improvements

**Must fix before deployment**

- Correct stale overall completion and define consistent revocation/downstream rules (BUG-001).
- Unify Registrar/legacy initialization and remove the CBE deadlock (BUG-002).
- Prevent HTTP access to integration tests, Git metadata and developer scratch files (BUG-003 / 008 / 009).
- Resolve and enforce evaluation prerequisite/privacy policy and Library return rules (BUG-004, BUG-005 and BUG-007). SASO conduct cases are outside scope; BUG-006 is withdrawn.
- Replace shared default credentials with a safe account activation/reset policy if the institution later changes its accepted password policy (BUG-010).
- Reject malformed requests safely, keep SQL/PHP details out of user responses, and enforce student submission gates (BUG-012–014).

**Should fix or settle before final defense**

- Confirm the authoritative per-college order and the meaning of staff preparation signing, then update the specification and tests consistently.
- Define evidence retention and remove superseded proof files safely (BUG-015); deny storage-root listing (BUG-016).
- Add regression coverage for the Registrar-created CBE path, stale completion, outstanding books/cases, and the real ordinary Dean approval path; the existing suites currently miss these cases.
- Set acceptance criteria for idle sessions, password-change session invalidation, backup/recovery and genuine evaluation completion.
- Complete the untested matrix items with physical devices and a controlled school pilot; test target hosting before claiming 500/1,000-user capacity.

**Optional future enhancements**

- Generic batch exclusions, password visibility, additional sortable lists and new certificate/print flows, if the school wants them.
- A complete installable PWA with an agreed manifest/update model. Keep confidential records out of offline caches unless separately designed and approved.
- Official evaluation-provider confirmation or another trustworthy verification method; avoid treating screenshot appearance or model confidence as proof.

These are recommendations only. No fixes were silently applied.

## 11. Final Readiness Assessment

**NOT READY.** The positive configured CCS path, ordinary role protections and the existing regression suite provide useful evidence of working functionality. They do not outweigh the reproduced incorrect final-clearance state, CBE startup deadlock, unauthorized integration-test mutation, and requirement/privacy gaps.

Acceptance requires fixing or formally resolving the high-impact findings, rerunning the affected tests against the intended workflow, and completing relevant untested real-device, authenticity and deployment-load cases. A separate Administrator or new batch-exclusion feature should not be added merely to make this audit's generic feature list look complete.

### Evidence and reproducibility

- [System inventory](SYSTEM_INVENTORY.md), [complete checklist](COMPLETE_CHECKLIST.md), [source coverage matrix](coverage-matrix.csv), [all verification results](all-test-results.json), [bug data](bugs.json), [source integrity](source-integrity.json).
- Scripts: inventory.mjs; prepare-sandbox.php; sandbox-data.php; run-safe-checks.mjs; sandbox-http.mjs; run-mysql-suites.mjs; scenario-tests.php; journey-tests.php; concurrency.mjs; render-browser-fixtures.php; browser-checks.mjs; extended-http.mjs.
- MySQL suite copies changed only their hard-coded test base URL to the isolated loopback server. The copied application code was unchanged. The isolated router reproduces the original Apache config/private/session denial rules; it does not establish production server behavior.
- Do not run the original database integration tests against school records. Recreate the disposable fixture first. Old evidence files preserve setup failures, while all-test-results.json contains the final normalized outcomes.
- The audit's own HTTP evidence path returned 403. Cleanup status is recorded in cleanup.json. No original student/account data was used in destructive tests, and no production-data reset or application migration was performed.
