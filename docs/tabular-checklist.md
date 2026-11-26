# OolaEdNerve — Development Checklist & Status

**Status date:** 25 November 2026  
**Purpose:** Single live development-status checklist for OolaEdNerve.  
**Status evidence rule:** `[P]` = production verified; `[L]` = locally implemented and verified; `[~]` = partially delivered; `[ ]` = pending; `[?]` = requires verification; `[D]` = deferred by explicit scope decision.

> **Important:** A local test pass is not evidence of production deployment. Percentages are approximate estimates of the listed module scope delivered, not audited KPIs. They do not imply production readiness. Historical status statements are retained only where consistent with the latest reported development evidence.

## 1. Executive status

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Platform and institution administration | 90% `[P]/[?]` | Core platform and institution workspaces, tenancy, RBAC and audit capabilities have previously been reported live. Reconfirm current production state against repository and target environment. |
| 2 | Security and operations | 75% `[~]` | Security controls and documentation substantially built; production credential rotation, privileged MFA enrolment and backup-restore drill remain open. |
| 3 | Academic core | 90% `[L]/[P]` | Academic sessions, programmes, subjects, hierarchy, syllabi and related academic enhancements delivered to varying environments; several recent enhancements remain locally verified only. |
| 4 | Question bank and paper setting | 75% `[~]` | Question bank, paper authoring, templates and approval workflow reported live. Confidential-paper access restriction and ratified-paper print watermark remain incomplete. |
| 5 | Examination and grading | 45% `[~]` | Core examinations, marks/results, hall tickets and revaluation reported live. F4/F5 enhancements are locally built and require production deployment verification; F6 and moderation remain open. |
| 6 | Admissions | 45% `[~]` | Intake, public application, status tracking and application decision pipeline reported live. Eligibility, counselling, quota, document verification and advanced admission controls remain open. |
| 7 | Student records | 40% `[~]` | Student profiles, enrolment/session association and result visibility available. CGPA, document vault, certificates and student self-service remain open. |
| 8 | Finance | 55% `[~]` | Fee heads/structures, allocation, ledger, manual payments and receipts available. Accounting, budgets, procurement, inventory and GST remain outside current scope. |
| 9 | Timetable and attendance | 85% `[~]` | Timetable, class registers, attendance summaries and exam-eligibility integration delivered. Holiday-aware timetable behaviour needs verification. |
| 10 | Notifications and shared services | 0% `[ ]` | Shared email/in-app notification service is not yet implemented; F5 notification integration remains open. |
| 11 | Reports and MIS | 0% `[ ]` | Report registry, exports and institutional MIS packs not implemented. |
| 12 | Mobile application | 5% `[D]` | Expo scaffold only; native mobile product functionality deferred. |
| 13 | Student and parent portals | 0% `[D]` | Deferred beyond the approved pilot slice. |
| 14 | HR, library, hostel, transport and inventory | 0% `[D]` | Deferred from the current pilot scope. |
| 15 | Full envisioned product | Approx. 45–55% | Directional estimate only; includes modules intentionally deferred from the pilot. |

## 2. Phase 0 — Baseline, source control and release hygiene

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Git repository, remote, branch and commit history | 0% `[ ]` | Confirm repository, active branch, remote, commit history and working-tree status. |
| 2 | Source backup and restore/clone verification | 0% `[ ]` | Commit or safely back up intended changes; verify a clean clone/restore. |
| 3 | Repository secret and generated-file audit | 0% `[ ]` | Check tracked `.env`, credentials, database dumps, logs, generated bundles and scratch archives. |
| 4 | `.gitignore` and `.gitattributes` | 0% `[ ]` | Verify repository and subproject-level rules. |
| 5 | Runtime/toolchain inventory | 0% `[ ]` | Record PHP, Composer, Node, npm, Laravel, React, Vite, Expo and database versions. |
| 6 | Clean-checkout API tests and web build | 0% `[ ]` | Run tests, lint and production build from a clean checkout. |
| 7 | Production URL, CORS and environment verification | 0% `[?]` | Confirm actual API/web URLs and build-time environment variables. |
| 8 | Production schema/migration reconciliation | 0% `[?]` | Compare production schema with repository migrations and record pending migrations. |
| 9 | Documentation-to-code reconciliation | 0% `[ ]` | Reconcile routes, models, middleware, services and UI with current documentation. |
| 10 | Safe migration/seed procedure | 0% `[ ]` | Confirm no destructive migration or seed command can run against valuable data. |

## 3. Security and operations — P0 / pilot blockers

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Production password hygiene | 70% `[~]` | Code defaults were hardened; rotate any existing production/demo accounts that may still use known passwords. |
| 2 | Demo-account removal | 0% `[ ]` | Disable/remove demo `*.ednerve.test` accounts from pilot production. |
| 3 | Privileged-user MFA enrolment | 70% `[~]` | MFA capability and rollout instructions exist; confirm all privileged production users are enrolled and recovery is tested. |
| 4 | MFA gates on sensitive operations | 80% `[~]` | Verify each pilot-sensitive action and its enforcement in the target environment. |
| 5 | Production configuration and secrets | 0% `[?]` | Verify `APP_ENV=production`, `APP_DEBUG=false`, production secrets and least-privilege DB credentials. |
| 6 | Web/mobile build secret hygiene | 0% `[?]` | Verify no secret is embedded in client build variables or repository history. |
| 7 | Audit coverage | 80% `[~]` | Audit infrastructure and several workflows exist; verify coverage for institution/user/role, admissions, exams, marks and results. |
| 8 | Sensitive-log minimisation | 0% `[ ]` | Verify logs exclude passwords, tokens, keys and unnecessary student data. |
| 9 | Database backup and restore drill | 0% `[ ]` | Execute a backup and restore to a separate scratch database; record evidence. |
| 10 | Incident response | 0% `[ ]` | Verify contact, evidence preservation and escalation procedure; conduct rehearsal. |
| 11 | Production health and smoke test | 0% `[?]` | Verify health endpoint and smoke test using a dedicated low-privilege account. |
| 12 | Security documentation | 100% `[L]` | Security, backup, incident-response, key-rotation and compliance documentation reported prepared; operational execution remains open. |

## 4. Architecture and shared foundations

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Tenant model — one institution per tenant | 100% `[L]/[P]` | Accepted architecture: one university/college is one tenant; organization units are divisions within that institution. |
| 2 | Institution-scoped data access | 90% `[~]` | Tenant isolation is a core implemented rule. Continue verifying all institution-owned reads/writes and cross-tenant relationships. |
| 3 | Organization-unit boundaries | 90% `[~]` | Verify organization-unit relationships cannot cross institution boundaries. |
| 4 | Legacy tenancy/design cleanup | 0% `[ ]` | Identify dead legacy design code; retire only after reference and test review. |
| 5 | Authoritative authorization pipeline | 80% `[~]` | Verify one consistent authorization pipeline and safely remove contradictory/unused mechanisms. |
| 6 | RBAC and permission catalogue | 90% `[~]` | Core RBAC/effective permissions reported implemented; reconcile permission catalogue and scoped permissions with actual routes/schema. |
| 7 | Audit model, service and event taxonomy | 90% `[~]` | Audit/hash-chain capability exists; verify event taxonomy and coverage against current implementation. |
| 8 | API contracts, validation and pagination | 80% `[~]` | Reconcile request/response contracts, error handling and pagination conventions. |
| 9 | Shared web design system | 80% `[~]` | Continue enforcing reusable layout, CRUD, form, table, filter and pagination components; avoid page-specific duplication. |

## 5. Platform and institution administration

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Platform authentication and session lifecycle | 90% `[P]/[?]` | Login/logout, session restoration and account-type enforcement require current smoke verification. |
| 2 | Institution provisioning | 100% `[P]` | Institution provisioning and first-administrator workflow previously reported live; recheck transactional behaviour. |
| 3 | Institution activation/deactivation | 90% `[P]/[?]` | Verify activation/deactivation behaviour in current production environment. |
| 4 | Institution profile and organization units | 100% `[P]` | Core administration capability previously reported live. |
| 5 | Institution user lifecycle | 90% `[P]/[?]` | Verify deactivation and token revocation behaviour. |
| 6 | Role assignment and scope | 90% `[P]/[?]` | Verify effective dates and organization-unit scope. |
| 7 | Platform/institution access boundaries | 90% `[P]/[?]` | Verify platform-admin and institution-admin access boundaries. |
| 8 | Workspace loading/empty/error/success states | 85% `[~]` | Review all key screens for consistent state handling. |

## 6. Academic core and student lifecycle

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Academic sessions and status rules | 100% `[P]` | Core academic-session capability reported live; recheck status rules. |
| 2 | Faculties, schools and academic units | 100% `[P]` | Three-tier academic hierarchy reported live. |
| 3 | Programmes, subjects and programme-course mapping | 100% `[P]` | Core catalogue and mappings reported live. |
| 4 | Subject components and prerequisites | 100% `[P]` | Theory/practical/tutorial components and prerequisite validation reported delivered. |
| 5 | Elective groups | 100% `[P]` | Capacity, required choices and programme/semester associations reported delivered. |
| 6 | Syllabus versioning and publication | 100% `[P]` | Draft/review/approve/publish, author/approver separation and immutable published versions reported live. |
| 7 | Course registration | 100% `[L]` | Per-session registration and elective enforcement built/tested locally; production deployment not confirmed. |
| 8 | Academic calendar | 100% `[L]` | Institution-scoped events and optional session linkage built/tested locally; production deployment not confirmed. |
| 9 | Academic sections/batches | 100% `[L]` | First-class `academic_sections` entity and CRUD built/tested locally; existing free-text timetable/exam section fields retained for compatibility. |
| 10 | Faculty workload (lite) | 100% `[L]` | Per-staff teaching/load allocations and screen built/tested locally; production deployment not confirmed. |
| 11 | Lesson plans (lite) | 100% `[L]` | Unit-wise teaching plan/progress and screen built/tested locally; production deployment not confirmed. |
| 12 | Applicant-to-student identity lifecycle | 70% `[~]` | Core application-to-decision/enrolment pipeline exists; verify end-to-end identity and edge cases. |
| 13 | Student profile and enrolment | 100% `[P]` | Student profile, programme/session association and lifecycle status reported live. |
| 14 | Student records audit and tenant scope | 85% `[~]` | Continue validating all mutations and institution boundaries. |
| 15 | SGPA/CGPA aggregation | 0% `[ ]` | Not implemented. |
| 16 | Student document vault | 0% `[ ]` | TC, migration, photo and category document vault not implemented. |
| 17 | Student certificates | 0% `[ ]` | Bonafide, transfer and migration certificate workflows remain open. |
| 18 | Student/alumni self-service | 0% `[D]` | Deferred beyond current pilot scope. |

## 7. Admissions

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Programme-wise intakes and application windows | 100% `[P]` | Core intake opening/closing and programme-wise capacity setup reported live; verify current rules. |
| 2 | Public applicant portal | 100% `[P]` | Application submission and public status-by-token reported live; verify token security. |
| 3 | Application review/decision/enrolment pipeline | 100% `[P]` | Core pipeline reported live. |
| 4 | Programme-specific eligibility rules | 0% `[ ]` | Marks, subjects, category and entrance cut-offs remain open. |
| 5 | Document upload and verification | 0% `[ ]` | Verification/rejection/rework workflow not implemented. |
| 6 | Merit calculation and tie-breaking | 0% `[ ]` | Define approved calculation, import/manual preparation and tie-breaking. |
| 7 | Reservation/quota and seat allocation | 0% `[ ]` | Requires pilot regulations and approved category/quota definitions. |
| 8 | Counselling and waitlist | 0% `[ ]` | Counselling rounds, offers, acceptance, withdrawal and waitlist promotion remain open. |
| 9 | Admission overrides and audit | 50% `[~]` | Existing decision audit needs verification; authorised override policy remains to be defined. |
| 10 | End-to-end applicant-to-enrolment tests | 50% `[~]` | Add synthetic end-to-end tests for eligibility, decisions and enrolment. |
| 11 | Application fee collection | 0% `[D]` | Manual-first approach; payment gateway deferred unless separately approved. |
| 12 | Bulk admission CSV import | 0% `[ ]` | Not implemented. |
| 13 | Applicant identity schema foundation | 100% `[L]` | Canonical `admission_applicants` and `admission_applicant_verification_tokens` tables, plus application linkage, are included in the revised create migrations; 101 migrations and seeders executed successfully locally. Applicant registration, email verification, password setup, separate applicant authentication and portal UI are not yet verified as implemented. |

## 8. Finance

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Fee heads | 100% `[P]` | Reported live. |
| 2 | Fee structures | 100% `[P]` | Course-wise/term-wise structures reported live. |
| 3 | Fee allocation | 100% `[P]` | Allocation engine reported live. |
| 4 | Student fee ledger | 100% `[P]` | Term-wise dues/ledger reported live. |
| 5 | Manual payments and receipts | 100% `[P]` | Manual payment and receipt workflows reported live. |
| 6 | Refunds and adjustments | 0% `[ ]` | Define and verify rules before pilot use. |
| 7 | Financial authorisation and audit | 70% `[~]` | Verify role restrictions and audit coverage for all financial mutations. |
| 8 | Double-entry accounting and chart of accounts | 0% `[D]` | Deferred to a later tier. |
| 9 | Budgets and variance | 0% `[D]` | Deferred. |
| 10 | Procurement and inventory/assets | 0% `[D]` | Deferred. |
| 11 | GST invoice handling | 0% `[D]` | Deferred. |
| 12 | Payment gateway | 0% `[D]` | Deferred; requires explicit approval. |

## 9. Timetable and attendance

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Timetable setup and grid | 100% `[P]` | Timetable grid reported live. |
| 2 | Faculty/own timetable view | 100% `[P]` | Own timetable view reported live. |
| 3 | Class register | 100% `[P]` | Per-period present/absent/leave capability reported live. |
| 4 | Student attendance summary | 100% `[P]` | Attendance summary reported live. |
| 5 | Faculty RBAC/own-class restrictions | 100% `[P]` | Own-class restrictions reported live. |
| 6 | Attendance threshold computation | 100% `[L]/[P]` | Windowed and per-subject threshold logic reported verified locally and integrated with exam eligibility; confirm target production state. |
| 7 | Exam eligibility gate and override | 100% `[L]/[P]` | Hall-ticket block and audited MFA override reported implemented; verify production configuration and audit. |
| 8 | Academic calendar/holiday-aware timetable | 0% `[ ]` | Holiday suppression and calendar interaction remain open. |
| 9 | Attendance correction audit | 70% `[~]` | Verify correction permissions and attributable audit records. |

## 10. Question bank and paper setting

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Encrypted question bank | 100% `[P]` | Subject association, model answers and tags reported live. |
| 2 | Manual paper authoring | 100% `[P]` | Manual authoring and print capability reported live. |
| 3 | Blueprint generation | 100% `[P]` | Blueprint generation reported live. |
| 4 | Paper templates | 100% `[P]` | Exam/unit-test/mid/sessional patterns reported live. |
| 5 | Paper approval/ratification SoD | 100% `[P]` | Draft → prepared → checked → approved → ratified with distinct actors and MFA at ratification reported live. |
| 6 | Approval trail and workflow UI | 100% `[P]` | Workflow trail and UI reported live. |
| 7 | Ratified-paper print watermark | 30% `[~]` | Partial UI work only; complete and verify watermark. |
| 8 | Confidential-paper access restriction | 0% `[ ]` | Custodian-only pre-exam access and audited access restriction not complete. |
| 9 | Institution-specific exam kinds | 100% `[P]` | Configurable exam kinds reported implemented; verify production data/configuration. |

## 11. Examination and grading — F4/F5/F6

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Exams, kinds, schedules, subjects and components | 100% `[P]` | Core examination setup reported live. |
| 2 | Marks entry and result computation/publication | 100% `[P]` | Marks/results workflow reported live with SoD and MFA gates; verify current production behaviour. |
| 3 | Result history/versioning | 100% `[P]` | ResultHistory versioning reported live. |
| 4 | Hall tickets | 100% `[P]` | Hall-ticket print capability reported live; eligibility gate separately tracked. |
| 5 | Revaluation | 100% `[P]` | Revaluation workflow reported live. |
| 6 | AMU ordinances and assessment schemes | 100% `[L]/[P]` | Configurable grading bands, pass criteria and assessment schemes built; LEGACY default retained, AMU-BTCSE opt-in. Confirm deployment/configuration before pilot use. |
| 7 | F4 exam scheduling | 100% `[L]` | Centres, rooms, capacity, sessions, ranked conflict detection and attendance eligibility/override built and locally verified; production deployment/migration not confirmed. |
| 8 | F5.1 duty assignment response/history | 100% `[L]` | Staff acceptance/decline, assignment replacement history, manager restrictions and auto-assignment corrections locally verified. |
| 9 | F5.2 cross-examination workload | 100% `[L]` | Workload includes eligible scheduled/published exams in same institution/session; excludes completed/different-session exams and declined/replaced duties; null session limited to target exam. Earlier baseline: 93 tests, 498 assertions. |
| 10 | F5.3-A versioned roster data model | 100% `[L]` | Institution-scoped roster versions, assignment linkage and version-1 backfill implemented. |
| 11 | F5.3-B version-aware assignment management | 100% `[L]` | Assignment create/update/delete and auto-assignment restricted to latest draft; staff response restricted to latest published roster. |
| 12 | F5.3-C roster lifecycle | 100% `[L]` | Draft → submitted → approved → published; rejection path; submitter/approver separation; published structural immutability. |
| 13 | F5.3-D audit and tenant isolation | 100% `[L]` | Lifecycle audit events, actor attribution, hash-chain verification and cross-institution access tests verified. |
| 14 | F5.3-E version history and workload consistency | 100% `[L]` | New version copies assigned/accepted duties as new records; copied status resets to assigned and requires fresh response; declined/replaced remain in prior history; history metadata/counts and latest-version-only workload/auto-assignment verified. |
| 15 | F5.3-E targeted regression tests | 100% `[L]` | `ExamDutyTest`: **42 passed, 419 assertions, 3.91 seconds** (reported 22 Nov 2026). Full project suite has not yet been reported after these latest changes. |
| 16 | F5 duty swap requests | 0% `[ ]` | Not implemented; confirm pilot need and define request/approval/cancellation states. |
| 17 | F5 roster-approver SoD refinement | 0% `[ ]` | Remaining approval/publisher policy and permission review. Do not assume existing lifecycle SoD covers every future role configuration. |
| 18 | F5 notification integration | 0% `[ ]` | Depends on shared notification service; not implemented. |
| 19 | F5 production deployment/migrations/seeds | 0% `[?]` | Local implementation only. Review additive migrations, seed requirements, backup and deployment plan before production rollout. |
| 20 | F6 practicals and vivas | 0% `[ ]` | Appointments, external letters, schedules, evaluations and discrepancy reporting not implemented. |
| 21 | Moderation/committee review | 0% `[ ]` | Post-marking moderation remains open if required by pilot ordinances. |
| 22 | Supplementary/improvement attempt rules | 0% `[ ]` | Define from approved ordinances before implementation. |
| 23 | Condonation/grace/max-backlog rules | 0% `[ ]` | Depends on approved ordinances. |
| 24 | F8 answer-script digitisation | 0% `[D]` | Deferred by scope decision; do not implement unless re-approved. |
| 25 | Digital marking on scanned scripts | 0% `[D]` | Dependent on F8; deferred. |
| 26 | Examination calendar grid/planner UI | 0% `[ ]` | Deferred from initial F4 scope; tabular scheduling view retained. |

## 12. Notifications, reports and other product surfaces

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Shared notification service | 0% `[ ]` | Email/in-app notification service not implemented. |
| 2 | F5 roster notifications | 0% `[ ]` | Pending shared notification service and event integration. |
| 3 | Reports and MIS registry | 0% `[ ]` | Not implemented. |
| 4 | CSV/PDF report exports | 0% `[ ]` | Not implemented as a shared reporting capability. |
| 5 | NAAC-style institutional packs | 0% `[ ]` | Not implemented. |
| 6 | Alumni directory | 0% `[D]` | Deferred. |
| 7 | Public website CMS | 15% `[D]` | Deprecated/descoped in pilot plan. |
| 8 | Mobile app | 5% `[D]` | Expo scaffold only; no product functionality. |
| 9 | Student and parent portals | 0% `[D]` | Deferred beyond approved pilot scope. |
| 10 | HR and employee lifecycle | 0% `[D]` | Deferred. |
| 11 | Library, hostel and transport | 0% `[D]` | Deferred. |
| 12 | OpenAPI specification | 0% `[ ]` | Not implemented. |
| 13 | SSO/SAML | 0% `[D]` | Deferred. |
| 14 | Internationalisation/multi-language | 0% `[D]` | Deferred. |
| 15 | AI services and broad analytics | 0% `[D]` | Deferred. |

## 13. Pilot release gate — must close before pilot sign-off

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Pilot scope and acceptance criteria | 0% `[ ]` | Obtain explicit pilot institution sign-off. |
| 2 | Production demo-credential rotation | 50% `[~]` | Code defaults hardened; production account rotation remains operator action. |
| 3 | Privileged production MFA | 50% `[~]` | Enrol privileged users and test recovery. |
| 4 | Backup restore drill | 0% `[ ]` | Execute and document restore to scratch DB. |
| 5 | Production environment/security configuration | 0% `[?]` | Verify configuration, secrets and DB privileges. |
| 6 | Critical tenant-isolation and authorization tests | 80% `[~]` | Local tests exist; run complete current regression and verify deployed behaviour. |
| 7 | Full backend test suite | 100% `[L]` | Full local `php artisan test` passed: **137 tests, 1,059 assertions, 11.15 seconds** (25 Nov 2026). This is local regression evidence, not production verification. |
| 8 | Web lint and production build | 0% `[?]` | Run after current frontend baseline is confirmed. |
| 9 | Migration review and backup | 70% `[~]` | Revised create-only migration set reviewed; **101 migrations executed successfully with seeders locally** (25 Nov 2026). Production backup, production schema comparison and deployment review remain open. |
| 10 | F4/F5 production deployment | 0% `[?]` | Not confirmed; schedule deployment only after migration and release review. |
| 11 | Production smoke tests | 0% `[ ]` | Execute after deployment using low-privilege and privileged test accounts as appropriate. |
| 12 | Pilot UAT | 0% `[ ]` | Confirm UAT slot, execute scenarios, record defects and obtain sign-off. |
| 13 | Incident-response rehearsal | 0% `[ ]` | Conduct simulated break-glass exercise. |
| 14 | DPDP notice and consent | 0% `[ ]` | Add/verify notice and consent on public application form. |
| 15 | Privacy/log minimisation | 0% `[ ]` | Verify PII minimisation and retention in logs. |
| 16 | Rollback and operator support | 0% `[ ]` | Verify rollback procedure and support/operator documentation. |
| 17 | Final go/no-go approval | 0% `[ ]` | Owner's explicit approval required; do not infer from test success. |

## 14. Deferred by default

| Sr. No. | Module / Feature | Status (%age) | Remark |
|---:|---|---:|---|
| 1 | Native mobile product functionality | 0% `[D]` | Deferred. |
| 2 | Public website CMS | 15% `[D]` | Descoped/deprecated in pilot plan. |
| 3 | Full double-entry accounting | 0% `[D]` | Deferred. |
| 4 | Procurement and inventory | 0% `[D]` | Deferred. |
| 5 | Payment gateway | 0% `[D]` | Deferred pending explicit approval. |
| 6 | SSO/SAML and external identity federation | 0% `[D]` | Deferred. |
| 7 | Student/parent portals beyond approved pilot slice | 0% `[D]` | Deferred. |
| 8 | HR/payroll, library, hostel and transport | 0% `[D]` | Deferred. |
| 9 | AI services, broad analytics/MIS and multi-language support | 0% `[D]` | Deferred. |
| 10 | Examination answer-script digitisation (F8) | 0% `[D]` | Deferred by scope decision. |

## 15. Current verified development evidence

> **Latest local baseline (25 November 2026):** 101 revised create-only migrations ran successfully with seeders; the full backend suite passed 137 tests and 1,059 assertions in 11.15 seconds. Neither result establishes production deployment or production readiness.


| Sr. No. | Verification item | Status | Remark |
|---:|---|---|---|
| 1 | F5.3-E targeted test suite | `[L]` | 42 tests passed, 419 assertions, 3.91 seconds; result reported 22 Nov 2026. |
| 2 | F5.3-E roster-version copy policy | `[L]` | Assigned/accepted assignments copied as new records; copied assignments reset to assigned; declined/replaced assignments retained in previous version. |
| 3 | F5.3-E roster history | `[L]` | Version history and lifecycle metadata/assignment counts covered by tests. |
| 4 | F5.3-E workload consistency | `[L]` | Workload and auto-assignment use latest roster version for each eligible examination. |
| 5 | F5.3-D lifecycle audit and tenant isolation | `[L]` | Lifecycle audit, actor attribution, hash-chain and cross-institution checks previously reported passing. |
| 6 | Full project backend suite | `[L]` | `php artisan test`: **137 passed, 1,059 assertions, 11.15 seconds** (25 Nov 2026). Full local backend regression suite passed after the revised migration work. |
| 7 | Revised create-only migration set and seeders | `[L]` | **101 migrations executed successfully with seeders** against the local `ednerve_db` database (25 Nov 2026). Database-level migration execution succeeded locally. |
| 8 | Production deployment of F4/F5 enhancements and revised schema | `[?]` | Not confirmed. Local migration and test success must not be represented as production deployment. |

## 16. Immediate next steps

1. Commit this checklist update with the revised migration set and current backend verification evidence.
2. Continue the Applicant Registration and Authentication module: applicant registration, email verification, password setup, isolated applicant authentication and applicant portal UI.
3. Run the web lint and production build when frontend files change.
4. Compare the revised local schema with the production schema; prepare a non-destructive production migration and backup/rollback plan.
5. Decide the exact pilot scope for the remaining F5 work: swap requests, roster-approver SoD and notifications.
6. Plan production deployment only after backup, migration review, security gates and an approved deployment window.
7. Close P0 pilot-readiness actions: production credential rotation, privileged MFA enrolment and backup restore drill.

**Status discipline:** Mark `[L]` only after local implementation and tests have actually passed. Mark `[P]` only after the relevant production behaviour has been verified. Keep `[?]` where the current target environment has not been checked. Move a deferred item out of `[D]` only through an explicit scope decision and update the project plan.
