# OolaEdNerve --- Development Documentation

**Purpose:** the small, maintained documentation set used to plan,
design, implement, test and release OolaEdNerve.

## Read in this order

1.  [`architecture.md`](architecture.md) --- technical structure and
    non-negotiable architecture decisions.
2.  [`design.md`](design.md) --- product scope, workspaces, domain
    workflows and module design.
3.  [`plan.md`](plan.md) --- delivery phases, sequencing and priorities.
4.  [`checklist.md`](checklist.md) --- actionable status and release
    gates.
5.  [`development-guide.md`](development-guide.md) --- rules for
    developers and AI coding assistants.
6.  [`quality-and-operations.md`](quality-and-operations.md) ---
    testing, deployment, security operations and recovery.

## Source-of-truth rules

-   Product scope and domain behaviour: `design.md`.
-   Technical architecture and data/tenant boundaries:
    `architecture.md`.
-   Milestones and sequencing: `plan.md`.
-   Current task status: `checklist.md`.
-   Coding conventions and safe-change rules: `development-guide.md`.
-   Test, release and operational procedures:
    `quality-and-operations.md`.
-   `changelog.md` remains the historical record; do not use it as the
    live task list.

## Status vocabulary

Use these labels consistently in the checklist and feature notes:

-   **Done --- production verified:** deployed and verified against the
    intended production environment.
-   **Done --- local verified:** implemented and verified locally;
    production deployment is not confirmed.
-   **Partial:** some required behaviour exists; remaining scope is
    listed.
-   **Pending:** not implemented or not yet verified.
-   **Deferred:** intentionally outside the current delivery scope.
-   **Needs verification:** documents disagree or evidence is
    insufficient.

Never convert a local build/test result into a production-complete
claim. Record the verification date and evidence for material release
claims.

## Repository and documentation maintenance

Keep this directory small. Add a separate feature specification only
when a feature has enough independent business rules, workflows, data
design or acceptance criteria to justify it. Avoid duplicate roadmaps,
duplicate status tables and repeated architecture descriptions.

Update the relevant canonical document in the same change when a
decision, workflow, API contract, data model, release gate or
implementation status changes.
