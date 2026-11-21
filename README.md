# OolaEdNerve (EdNerve)

> A comprehensive Higher Education ERP product built by **Oola Systems**.

OolaEdNerve is a multi-institution platform for higher-education institutions (universities, colleges),
combining platform-level administration (Oola Systems) with institution-level ERP operations
(registrar, admissions, examinations, finance, HR, students, applicants, parents/guardians, auditors).

**Status (as of 2026-11-15):** early MVP build — platform-admin institution management works end-to-end;
institution-side ERP modules, mobile app, tests and CI/deploy automation are not yet implemented.
See [`docs/gap-analysis.md`](docs/gap-analysis.md) for the honest state of the project.

---

## Repository layout

This is a multi-part codebase (no monorepo tooling). Each app is managed independently.

| Directory        | App                              | Stack                                                        |
| ---------------- | -------------------------------- | ------------------------------------------------------------ |
| `ednerve-api/`   | Backend API                      | PHP 8.3 · Laravel 13 · Laravel Sanctum · MySQL (XAMPP local) |
| `ednerve-web/`   | Web frontend (platform admin)    | React 19 · TypeScript ~6 · Vite 8 · Tailwind CSS 4           |
| `ednerve-mobile/`| Mobile app (not started)         | Expo SDK ~58 (React Native 0.88) · Expo Router · TypeScript  |
| `scripts.ps1`    | Local env check (Windows/XAMPP)  | —                                                            |

## Agreed technology stack

- **Backend API:** Laravel + MySQL (REST, token auth via Laravel Sanctum)
- **Web:** React + Vite + TypeScript (SPA)
- **Mobile:** React Native + Expo
- **Source control:** GitHub (`https://github.com/oolasystems/EdNerve.git`)
- **API hosting:** MilesWeb shared hosting — `https://ednerve-api.oolasystems.com`
- **Web hosting:** Cloudflare Pages (SPA) — `https://ednerve.oolasystems.com`
- **Architecture:** Modular monolith for the MVP, with clear module boundaries kept for a possible
  future migration to microservices

## Development strategy

- **Zero additional expenditure until the MVP is completed.**
- **Sole developer** handling architecture, coding, testing and deployment.
- Prefer free tooling (GitHub, Cloudflare Pages free tier, Laravel/Ecosystem free packages,
  free uptime monitoring) and avoid paid services until the MVP ships.

## Quick start (local, Windows + XAMPP)

Prerequisites: PHP 8.3, Composer, Node.js/npm, MySQL (XAMPP), Git. Run `.\scripts.ps1` to verify.

```powershell
# Backend API
cd ednerve-api
composer install
copy .env.example .env        # then configure DB_* (local uses MySQL database `ednerve_db`)
php artisan key:generate
php artisan migrate           # do NOT use migrate:fresh — destructive
php artisan db:seed           # seeds demo institutions/roles/users (LOCAL ONLY — see docs/known-issues.md)
php artisan serve             # http://127.0.0.1:8000/api

# Web
cd ..\ednerve-web
npm install
npm run dev                   # http://localhost:5173

# Mobile (scaffold only — no product code yet)
cd ..\ednerve-mobile
npm install
npx expo start
```

See [`docs/setup-and-deployment.md`](docs/setup-and-deployment.md) for full local setup,
environment configuration and the MilesWeb / Cloudflare Pages deployment workflow.

## Documentation index

| Document                                          | Contents                                              |
| ------------------------------------------------- | ----------------------------------------------------- |
| [`docs/prd.md`](docs/prd.md)                      | Product requirements (working draft — awaits ratification) |
| [`docs/plan.md`](docs/plan.md)                    | Project plan: milestones M0–M5, risk register          |
| [`docs/tasks.md`](docs/tasks.md)                  | Actionable backlog (T-xxx), phased                     |
| [`docs/architecture.md`](docs/architecture.md)    | Architecture, tenancy, authN/authZ, data model        |
| [`docs/api.md`](docs/api.md)                      | HTTP API reference                                    |
| [`docs/database.md`](docs/database.md)            | Schema reference & seed-data guide                    |
| [`docs/deep-study.md`](docs/deep-study.md)        | Verified deep-dive of the entire codebase             |
| [`docs/setup-and-deployment.md`](docs/setup-and-deployment.md) | Local dev, env vars, MilesWeb + Cloudflare Pages deploy |
| [`docs/testing.md`](docs/testing.md)              | Test strategy & priority test list                    |
| [`docs/security.md`](docs/security.md)            | Threat model, hardening checklists, incident response |
| [`docs/agent.md`](docs/agent.md)                  | Playbook for AI agents / contributors                 |
| [`docs/troubleshooting.md`](docs/troubleshooting.md) | Symptom → cause → fix                               |
| [`docs/known-issues.md`](docs/known-issues.md)    | Bugs, dead code, security and config problems         |
| [`docs/gap-analysis.md`](docs/gap-analysis.md)    | Progress vs. MVP, outstanding work                    |
| [`docs/roadmap.md`](docs/roadmap.md)              | Suggested MVP sequencing (zero-cost, solo developer)  |
| [`docs/open-decisions.md`](docs/open-decisions.md)| Decisions pending (tenancy model, scope, hosting)     |
| [`docs/changelog.md`](docs/changelog.md)          | Reconstructed change history                          |

## Important caveat

The product document containing the recorded discussions/decisions (Word file) was **not available**
when these docs were written. Every statement here is derived from the code, the in-repo implementation
notes (`ednerve-api/IMPLEMENTATION_STEPS_*.md`) and git state as verified on **2026-11-15**.
Where the two disagree, both are recorded — see
[`docs/open-decisions.md`](docs/open-decisions.md). If the document is provided later, these docs
should be reconciled against it.

## License / ownership

Proprietary software of **Oola Systems**. All rights reserved unless stated otherwise by a
`LICENSE` file in a sub-project.
