# System inventory

Only application source, table definitions and nonpersonal configuration were used for discovery. No original student/account rows were copied into the audit database.

## Architecture

PHP 8.2 server-rendered pages, PDO/MySQL, PHP cookie sessions, CSRF-protected form handlers, CSS/vanilla JavaScript interfaces, protected upload handlers, and a service worker caching the public outage screen/logo. No package-manager application framework or web-app manifest was discovered.

Data moves from student form handlers to per-semester clearance/requirement/source records, then office actions update signatures, audit logs and notifications. Student pages read those records on refresh; notifications use a separate JSON endpoint. Registrar creates/enrolls semesters and controls readiness/opening.

## Roles

STUDENT, ORG_STAFF, DEAN, SASO_STAFF, STUDENT_COUNCIL_STAFF, LIBRARY_STAFF, BOOKSTORE_STAFF, CLINIC_STAFF, FACILITIES_STAFF, COMLAB_STAFF, ACCOUNTING_STAFF, REGISTRAR. ADMIN is deliberately removed; Registrar provides management. Organization and Dean assignments are college-scoped.

## Stations

| Code | Name | Parent station ID | Configured sort order |
|---|---|---|---|
| ORGANIZATION | Organization | Primary | 2 |
| DEAN | Dean | Primary | 1 |
| STUDENT_COUNCIL | Student Council | 3 | 1 |
| LIBRARY | Library | 3 | 2 |
| BOOKSTORE | Bookstore | 3 | 3 |
| SASO | SASO | Primary | 3 |
| CLINIC | Clinic | 3 | 4 |
| FACILITIES | Facilities | Primary | 4 |
| ACCOUNTING | Accounting | Primary | 6 |
| REGISTRAR | Registrar | Primary | 7 |
| COMLAB | Computer Laboratory | Primary | 5 |

## Requirements

| Station | Requirement code | Name | College ID | Year range | Semester | Required | Status |
|---|---|---|---|---|---|---|---|
| DEAN | LEGACY_2 | Dean Approval | All | Any to Any | All | 1 | ACTIVE |
| STUDENT_COUNCIL | LEGACY_4 | Required Student Council Events | All | Any to Any | All | 1 | ACTIVE |
| DEAN | CCS_FACULTY_EVALUATION | Faculty Evaluation Completion | 1 | Any to 3 | All | 1 | ACTIVE |
| DEAN | CBE_YBC_APPROVAL | CBE Organization (YBC) Sign-off | 2 | 1 to 3 | All | 0 | INACTIVE |
| DEAN | CBE_OJT_REQUIREMENTS | OJT Requirements | 2 | 4 to 4 | All | 1 | ACTIVE |
| DEAN | CCS_GRADUATION_APPLICATION | Application for Graduation | 1 | 4 to 4 | 2ND | 1 | ACTIVE |
| DEAN | CCS_FOURTH_YEAR_FACULTY_EVALUATION | Faculty Evaluation Completion | 1 | 4 to 4 | 1ST | 1 | ACTIVE |
| ORGANIZATION | LEGACY_1 | Required Organization Events and Meetings | All | Any to Any | All | 1 | ACTIVE |
| LIBRARY | LEGACY_5 | Library Card | All | Any to Any | All | 1 | ACTIVE |
| LIBRARY | LEGACY_6 | Borrowed Books | All | Any to Any | All | 1 | ACTIVE |
| SASO | LEGACY_3 | School Rules Violations | All | Any to Any | All | 1 | ACTIVE |
| BOOKSTORE | LEGACY_7 | Required Event Fees | All | Any to Any | All | 1 | ACTIVE |
| CLINIC | LEGACY_8 | School Medical Requirement | All | Any to Any | All | 1 | ACTIVE |
| FACILITIES | LEGACY_9 | Facilities Violations | All | Any to Any | All | 1 | ACTIVE |
| ACCOUNTING | LEGACY_10 | Financial Obligations | All | Any to Any | All | 1 | ACTIVE |
| REGISTRAR | REGISTRAR_COMPLETE_DOCUMENTS | Complete Documents | All | Any to Any | All | 1 | ACTIVE |
| REGISTRAR | REGISTRAR_ALL_OFFICES_SIGNED | Signed by All Offices | All | Any to Any | All | 1 | ACTIVE |

## Source routes, functions and controls

See [routes.csv](routes.csv), [functions.csv](functions.csv), [controls.csv](controls.csv), [actions.csv](actions.csv), and the [coverage matrix](coverage-matrix.csv). Source markers identify 38 UI/template/wrapper files, not 38 guaranteed independent pages. Thirty-one role UI entry URLs were probed independently in the isolated HTTP harness, plus the public login page. Shared profile and conditional views are inventoried; any unexecuted interaction is not assumed to pass.
