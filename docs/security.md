# EdNerve --- Security Architecture and Security Requirements

**Document status:** Working security baseline\
**Product:** OolaEdNerve / EdNerve\
**Owner:** Oola Systems\
**Applies to:** Laravel API, MySQL database, React web application,
future React Native mobile application, hosting, integrations and
operational processes.

> This document defines the security direction and requirements for
> EdNerve. It is not evidence that every control is already implemented.
> Implementation status must be recorded in `checklist.md` and verified
> against source code and the target environment.

## 1. Purpose and security objectives

EdNerve processes institutional, personal, academic and potentially
highly confidential information. Security must be designed into the
product lifecycle rather than added as a final deployment task.

The objectives are to:

1.  Protect confidentiality, integrity and availability of institutional
    and academic information.
2.  Enforce strict isolation between institutions.
3.  Ensure users and services receive only the access required for their
    duties.
4.  Protect high-impact academic workflows, especially examination
    papers, marks, results and approvals.
5.  Make material actions attributable and tamper-evident.
6.  Minimise collection, exposure and retention of personal and
    confidential data.
7.  Detect, investigate and recover from security incidents.
8.  Apply consistent controls across development, testing, deployment
    and production.

## 2. Security principles

### 2.1 Zero Trust

Do not implicitly trust a request because it originates from an
authenticated user, a known network, a frontend route or an internal
service.

For every protected request:

-   Authenticate the actor or service.
-   Authorize the specific action against the target resource.
-   Verify institution and applicable organization-unit boundaries.
-   Validate the current state and permitted workflow transition.
-   Apply additional verification for sensitive actions where required.
-   Record an appropriate audit event.

Authentication alone does not grant permission. A valid token must not
bypass authorization, tenant isolation or workflow rules.

### 2.2 Least privilege

-   Grant only the permissions required for a user's current duties.
-   Prefer scoped assignments over broad institution-wide access where
    practical.
-   Separate platform administration from institution administration.
-   Avoid shared administrator accounts.
-   Review privileged accounts and role assignments periodically.
-   Revoke access promptly when an account, role or assignment is
    disabled or ends.

### 2.3 Secure by default

-   Deny access when authorization or tenant context cannot be
    established.
-   Do not expose sensitive fields by default in API resources.
-   Keep debug mode disabled in production.
-   Do not seed demo users or sample confidential data in production.
-   Keep secrets out of source control and client-side bundles.
-   Use explicit allow-lists for mass assignment, exports and file
    access.

### 2.4 Defence in depth

Use complementary controls at the API, database, application, hosting
and operational layers. Frontend hiding is useful for user experience
but is never a substitute for backend enforcement.

## 3. Identity, authentication and sessions

### 3.1 Identity model

The current product architecture records one institution per institution
user:

-   Platform users have `account_type = platform` and no institution
    association.
-   Institution users have `account_type = institution` and exactly one
    `institution_id`.
-   Organization-unit assignments provide additional scope within the
    institution.
-   A user must not be able to switch institution context by changing a
    request parameter.

This is the recorded Design A tenancy model. Any change to this model
requires an explicit architecture decision and a security review.

### 3.2 Authentication requirements

-   Use the approved Laravel Sanctum authentication mechanism.
-   Enforce strong password storage using Laravel's supported password
    hashing configuration.
-   Apply rate limits to login, password reset, OTP and other
    authentication endpoints.
-   Avoid account-enumeration disclosures in login and recovery
    responses.
-   Use secure, expiring, single-use password-reset and verification
    tokens.
-   Revoke active tokens/sessions when an account is disabled,
    compromised or has its credentials reset, according to the session
    design.
-   Record security-relevant authentication events without logging
    passwords, raw tokens or recovery codes.

### 3.3 Multi-factor authentication

MFA is required for privileged accounts and should be enforced for
sensitive actions based on risk.

The implementation must define:

-   Which account classes and roles must enrol in MFA.
-   Which actions require step-up authentication (for example,
    privileged account changes, examination ratification, result
    publication or key security settings).
-   MFA challenge lifetime and replay prevention.
-   Recovery-code generation, one-time use, storage and regeneration.
-   Lost-device recovery and administrator-assisted recovery with audit.
-   Enrolment, disablement and reset permissions.

Do not treat a UI prompt as enforcement. The API must validate MFA state
for protected operations.

### 3.4 Session and token security

-   Define token/session expiry and revocation behaviour.
-   Provide users and authorised administrators with appropriate session
    visibility and revocation controls.
-   Revoke sessions after password reset, account disablement or
    suspected compromise.
-   Store tokens securely in the client. The current web implementation
    has been documented as using `localStorage`; assess the XSS exposure
    and evaluate an HttpOnly, Secure, SameSite cookie approach if the
    deployment topology supports it. Do not change the authentication
    model without testing CORS, CSRF, API and hosting implications.
-   Never place long-lived secrets in URLs or browser logs.
-   Use HTTPS for all production authentication and application traffic.

## 4. Authorization and tenant isolation

### 4.1 Authorization model

The backend is authoritative for:

-   Account type (platform or institution).
-   Role and permission.
-   Institution scope.
-   Organization-unit scope.
-   Resource ownership and relationship constraints.
-   Workflow state and separation-of-duties rules.

The frontend may hide routes, menus and buttons based on effective
permissions, but every API operation must independently enforce
authorization.

### 4.2 Tenant isolation requirements

For every institution-owned record:

-   Resolve the institution from the authenticated server-side
    identity/context.
-   Apply institution scope to reads, updates, deletes, exports, counts,
    search, reports and file downloads.
-   Reject attempts to reference records belonging to another
    institution.
-   Validate that related records (student, programme, course, section,
    fee, examination, organization unit, attachment, etc.) belong to the
    same institution.
-   Do not trust a client-supplied `institution_id` as proof of scope.
-   Ensure background jobs, scheduled tasks, exports and notifications
    preserve institution context.
-   Include cross-tenant negative tests for every important resource
    family.

Platform-level cross-institution access must be explicit, narrowly
permissioned and audited. It must not be achieved by silently bypassing
tenant filters throughout the application.

### 4.3 Organization-unit scope

Organization units are divisions within an institution, not separate
tenants. Where a user's assignment is restricted to a faculty, school,
department or other unit:

-   Enforce the scope in the API/service/query layer.
-   Validate parent-child relationships and prevent cross-institution
    links.
-   Define whether permissions inherit down the hierarchy; do not assume
    inheritance.
-   Test direct object access and bulk/export endpoints, not only list
    screens.

### 4.4 Permission implementation

The supplied project history reports RBAC and effective-permission work,
while older design notes describe gaps. Therefore, verify the current
implementation before extending it.

The security review must inspect:

-   Permission catalogue and stable permission codes.
-   Role-to-permission mapping.
-   User-to-role assignments and scope.
-   Middleware, policies and service-level checks.
-   Deny-by-default behaviour.
-   Cache invalidation when assignments change.
-   API route coverage and negative tests.

Avoid maintaining two competing authorization systems. Document one
authoritative decision path.

## 5. Sensitive academic workflows and separation of duties

Some EdNerve operations have a high impact on academic integrity. Apply
explicit state transitions, authorization and audit controls.

### 5.1 Examination papers

-   Restrict access to authorised examination personnel and only for the
    required period.
-   Separate paper preparation, checking, approval and ratification
    roles where the institution's process requires it.
-   Prevent an actor from approving their own work where separation of
    duties is required.
-   Protect downloads and previews with server-side authorization.
-   Use time-limited access where appropriate.
-   Consider visible user/time watermarking for confidential previews
    and exports.
-   Avoid storing confidential papers in public web roots or predictable
    public URLs.
-   Audit creation, viewing where justified, download, modification,
    approval, ratification and release events.

### 5.2 Marks and results

-   Restrict marks entry, modification, moderation, approval and
    publication to authorised roles.
-   Enforce the permitted result state machine on the server.
-   Record who changed a mark, the previous and new values where policy
    permits, reason, time and related assessment.
-   Require an approval/second-person control for high-impact changes
    according to institutional policy.
-   Preserve result history; do not silently overwrite published
    results.
-   Gate result publication and other high-impact actions with
    MFA/step-up authentication where configured.
-   Ensure student-facing endpoints reveal only the student's permitted
    information and only after the applicable release state.

### 5.3 Admissions and finance

-   Audit eligibility overrides, merit changes, quota/seat allocation,
    offer decisions and enrolment transitions.
-   Protect applicant documents and prevent cross-applicant and
    cross-institution access.
-   Audit fee adjustments, refunds, waivers, receipt cancellation and
    other material financial changes.
-   Apply maker-checker approval where the institution's risk and
    process require it.

## 6. Audit logging and tamper evidence

EdNerve's recorded direction is to use tamper-evident audit records with
hash chaining and signed checkpoints. Blockchain is not a required
platform component.

### 6.1 Audit event requirements

For material events, capture as appropriate:

-   Event identifier and event type.
-   Actor identity and account type.
-   Institution and organization-unit context.
-   Target resource type and identifier.
-   Action and outcome.
-   Timestamp from a trusted server clock.
-   Correlation/request identifier.
-   Relevant reason or approval reference.
-   Minimal before/after metadata where justified and safe.

Do not store passwords, access tokens, recovery codes, private keys or
unnecessary sensitive record contents in audit logs.

### 6.2 Integrity controls

-   Hash-chain audit entries using a documented canonical serialization
    and algorithm.
-   Sign periodic checkpoints using a signing key held separately from
    ordinary application data.
-   Define checkpoint frequency, verification process and failure
    alerting.
-   Restrict write access to audit tables and signing operations.
-   Ensure ordinary application administrators cannot rewrite history
    through application features.
-   Export or preserve checkpoints in a separately protected location
    where feasible.
-   Test detection of modified, deleted, reordered and missing records.

Hash chaining alone does not prevent a privileged attacker from
rewriting the entire chain. Independent signed checkpoints and protected
key custody are important to strengthen detection.

### 6.3 Audit availability and retention

Define retention by record category, institutional policy and applicable
law. Do not apply one universal retention period to all academic,
financial, security and personal data. Ensure retention settings do not
undermine legal holds, investigations or required academic record
preservation.

## 7. Data classification and privacy

### 7.1 Classification levels

Use a practical classification scheme:

  --------------------------------------------------------------------------
  Classification          Examples                   Minimum handling
  ----------------------- -------------------------- -----------------------
  Public                  Approved public            Integrity and
                          prospectus, published      availability controls
                          notices                    

  Internal                Routine operational        Authenticated access;
                          procedures and non-public  avoid public exposure
                          institutional information  

  Confidential            Student profiles,          Scoped access, secure
                          applications, fee records, transport, controlled
                          staff information          exports and retention

  Highly Confidential     Examination papers before  Strict need-to-know
                          release, credentials, MFA  access, enhanced audit,
                          recovery material, signing strong storage/access
                          keys, sensitive            controls and restricted
                          disciplinary/counselling   handling
                          records                    
  --------------------------------------------------------------------------

The institution may define additional categories. Classification is
based on the information and its context, not merely the database table.

### 7.2 Data minimisation

-   Collect only information needed for a defined institutional purpose.
-   Do not return entire database models from APIs.
-   Use explicit response resources and field allow-lists.
-   Mask sensitive fields in lists, logs and support diagnostics.
-   Avoid copying production personal data into development, test,
    screenshots or AI prompts.
-   Use synthetic or irreversibly anonymised data for demonstrations and
    tests.

### 7.3 Retention, deletion and legal review

Retention and deletion rules must be established per data category with
the institution and qualified legal/compliance advice. Academic records,
financial records, identity documents, security logs and applicant data
may have different requirements.

For each category, document: - Purpose and owner. - Legal/contractual
basis as confirmed by the institution. - Retention trigger and period. -
Legal-hold or investigation exception. - Disposal/anonymisation
method. - Backup expiry behaviour. - Who can approve exceptions.

Do not hard-code a generic period such as "graduation plus two/seven
years" as a universal policy.

### 7.4 Data subject and disclosure processes

Define an institution-governed process for access, correction, export,
restriction, deletion where legally available, and disclosure to third
parties. Verify identity and authority before responding. Maintain an
audit trail of fulfilment and exceptions.

## 8. Encryption and cryptographic key management

### 8.1 Transport and storage

-   Enforce HTTPS/TLS for production web, API and integration traffic.
-   Use hosting/database encryption-at-rest capabilities where available
    and verified.
-   Restrict database, backup and file-storage access.
-   Encrypt highly confidential files or fields where the threat model
    and hosting capabilities justify it.
-   Do not claim a field is encrypted merely because the hosting
    provider encrypts disks or backups.

### 8.2 Distinguish keys by purpose

Do not treat all secrets as one interchangeable key.

-   **Laravel `APP_KEY`:** framework application encryption purpose;
    protect and back up securely. Rotation can invalidate encrypted
    application data and must be planned.
-   **Database credentials:** grant least privilege; rotate when exposed
    or according to policy.
-   **Audit-signing private key:** separate from `APP_KEY`, database
    credentials and ordinary application secrets. Restrict use and
    access; maintain protected backup and rotation/recovery procedures.
-   **External API credentials:** use only server-side and grant the
    minimum required scopes.
-   **TLS private keys:** manage through the hosting/platform
    certificate process.

### 8.3 Key lifecycle

For each key, document owner, purpose, storage, access, backup,
rotation, revocation and compromise response. Prefer a managed secret
store or hardware-backed key facility when available and affordable. If
the shared-hosting environment cannot provide these capabilities,
document the residual risk and compensating controls.

Never commit keys or include them in frontend build variables, logs,
screenshots or support tickets.

## 9. Application and API security

-   Validate all input server-side; treat client data as untrusted.
-   Use parameterised database access and safe ORM/query-builder
    patterns.
-   Protect against SQL injection, XSS, CSRF, SSRF, mass assignment,
    insecure direct object references and unsafe file uploads.
-   Encode output appropriately and use a restrictive Content Security
    Policy where compatible with the application.
-   Apply rate limits to authentication, public application, OTP, search
    and resource-intensive endpoints.
-   Set request size, pagination and upload limits.
-   Validate file extension, MIME type and content; generate storage
    names; store outside public web root; scan files where feasible.
-   Use secure HTTP headers and avoid leaking stack traces or internal
    details.
-   Keep dependencies patched; review dependency advisories before
    upgrades.
-   Restrict CORS to intended origins; CORS is not an authorization
    mechanism.
-   Use explicit API versioning/compatibility practices for changes that
    affect clients.

## 10. Infrastructure and deployment security

-   Keep production debug mode disabled.
-   Use separate credentials and configuration for local, test and
    production environments.
-   Use least-privilege database users.
-   Restrict SSH/control-panel access and use MFA where supported.
-   Keep application files, uploads, logs and backups separated with
    appropriate permissions.
-   Ensure public web document roots expose only intended public assets
    and entry points.
-   Do not expose `.env`, source maps containing sensitive data,
    database dumps, logs, backups or internal tools.
-   Review Cloudflare and hosting access controls, DNS, TLS, CORS and
    deployment credentials.
-   Keep CI/CD secrets in protected GitHub repository/environment
    secrets; never put them in workflow source.
-   Review workflow permissions and pin or update third-party actions
    responsibly.
-   Require review before production deployment and migration execution.

## 11. Secure AI and external service use

AI features and developer AI tools must follow data classification and
provider-specific data handling terms.

-   Do not submit real student records, applicant documents,
    confidential examination papers, credentials, private keys,
    production dumps or unreleased institutional data to a consumer AI
    service.
-   Use synthetic, redacted or properly de-identified examples for
    development and troubleshooting.
-   Before sending any confidential data to an external model/API,
    verify the exact account tier, endpoint, retention policy, training
    policy, logging behaviour, region, subprocessors and contractual
    terms.
-   Do not assume that "API" automatically means zero data retention.
-   Where zero-retention or modified-abuse-monitoring controls are
    required, confirm eligibility and endpoint/feature coverage directly
    with the provider before use.
-   Prefer local inference for highly confidential development material
    when appropriate, while remembering that local logs, backups,
    plugins and machine access still require protection.
-   Treat model output as untrusted input. Validate generated SQL, code,
    migrations, permissions and business decisions before execution.
-   Do not allow an AI agent to execute production migrations, access
    production secrets or make privileged production changes without
    explicit human approval and controlled procedures.

## 12. Security monitoring and incident response

Monitor for: - Repeated failed logins, suspicious password resets and
MFA recovery. - Privilege escalation, role changes and unusual
platform-level access. - Cross-tenant access denials and unusual
export/download activity. - Examination-paper access outside expected
roles or windows. - Unexpected marks/result changes and approval
sequence violations. - Audit-chain or checkpoint verification
failures. - Unexpected application errors, traffic spikes and resource
exhaustion.

Incident response:

1.  Identify and record the suspected incident, time, affected systems
    and initial evidence.
2.  Contain the issue and revoke compromised credentials/tokens as
    appropriate.
3.  Preserve logs, audit records, relevant checkpoints and forensic
    evidence.
4.  Assess affected institutions, records, confidentiality, integrity
    and availability.
5.  Recover from a known-good state only after assessing cause and
    integrity.
6.  Follow the institution's and applicable legal/contractual
    notification process.
7.  Document root cause, corrective actions, owners and regression
    tests.

Define operational contacts, severity levels, response targets and
notification obligations separately with the institution. Do not assume
a generic legal notification deadline.

## 13. Security testing and verification

Security verification must be part of normal development and release.

Minimum tests: - Cross-tenant read, update, delete, search, count,
export and file-download denial. - Organization-unit scope and
relationship integrity. - Role/permission denial for every privileged
endpoint. - Inactive account, expired/revoked token and MFA-required
action behaviour. - Workflow transition and separation-of-duties
negative tests. - Audit event creation and tamper/checkpoint
verification. - File upload validation and unauthorised download
denial. - Rate-limit and authentication recovery behaviour. - Production
configuration and secret-exposure checks.

Use automated tests where practical, plus focused manual review and
penetration testing before a broad production rollout. Record findings,
severity, remediation and retest evidence.

## 14. Security responsibilities

  -----------------------------------------------------------------------
  Area                                Responsibility
  ----------------------------------- -----------------------------------
  Product owner                       Approves risk, scope, security
                                      priorities and go-live

  Development                         Implements secure design,
                                      validation, authorization and tests

  Institution administrator           Manages institution users, roles,
                                      data governance and local
                                      procedures

  Hosting/platform operator           Protects infrastructure, platform
                                      credentials, backups and
                                      availability within provider
                                      capabilities

  Data protection/legal advisor       Confirms applicable legal basis,
                                      retention, disclosure and
                                      notification obligations
  -----------------------------------------------------------------------

Responsibilities must be made explicit in pilot agreements; do not
assume the hosting provider is responsible for application-level
security.

## 15. Security release gate

A production pilot must not proceed until the owner has verified and
accepted:

-   Tenant-isolation and authorization tests pass.
-   Privileged MFA and recovery are configured and tested.
-   Demo/default credentials and test accounts are removed or disabled.
-   Production secrets are protected and not present in client bundles
    or source control.
-   Sensitive data and files are access-controlled.
-   Audit logging and tamper-evidence verification work for critical
    events.
-   Backup and restore have been demonstrated.
-   Production configuration, TLS, CORS and deployment access are
    reviewed.
-   Incident response and support contacts are known.
-   Critical/high security findings are resolved or explicitly
    risk-accepted by the owner.

Track each control's actual status and evidence in `checklist.md`. This
document defines requirements; it does not certify compliance or claim
that controls are implemented.
