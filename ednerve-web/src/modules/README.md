# Module layout convention

```
modules/
  <module>/                 ← institution Administration workspace (the product default)
  platform-<module>/        ← platform Administration (Oola Systems) workspace
  auth/                     ← cross-workspace (guards, login, MFA)
  shared/                   ← cross-workspace placeholder / 404 primitives
  public-portal/            ← public (unauthenticated) applicant/verify pages
```

Rules:

1. **Platform-prefix for platform-workspace modules** — anything under `/platform/*` routes from the platform rail uses `platform-*`.
2. **Unprefixed modules belong to the institution workspace** — anything under `/institution/*` routes from the institution rail uses the plain domain name (`academic`, `finance`, `examinations`, …).
3. **Never nest modules inside other domain modules** — one module per domain, flat, no parent-child domain folders.
4. **Stylesheet ownership follows module boundaries.** Shared cross-module patterns live in `styles/module-patterns.css` and are imported once by `index.css`; institution-only styles live in `modules/institution/institution.css`; feature styles live with their owning modules. A module must not import another module's stylesheet to obtain generic styles. Promote genuinely shared selectors to `styles/module-patterns.css` instead. Global tokens, application-shell layout, and shared UI/CRUD styles remain in `styles/`.
5. **No duplication** — platform vs institution workspaces are separate folders even when names look similar (`platform-users` = platform admin staff management; `institution` / `institution/users` = institution-side users).
6. If a domain is missing, create the module folder first, then wire the rail — not the reverse.
