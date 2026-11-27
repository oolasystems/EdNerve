# EdNerve Web — Final Design-System Consistency Audit

**Source reviewed:** `src(20261126-193933).zip`  
**Scope:** React/TypeScript source, shared UI/CRUD components, CSS files, stylesheet imports, native HTML control usage, inline styles, and selector duplication.

## Executive summary

The current source has a substantially more coherent design-system structure than the earlier snapshots. The shared UI components, CRUD components, CSS ownership split, and stylesheet import order are present.

This audit did **not** identify a safe justification for a broad automated replacement of native HTML controls or inline styles. Many remaining native elements serve specialized workflows, while numerous inline styles are dynamic presentation values. Replacing them indiscriminately could change behavior or visual output.

The remaining work is best handled as targeted adoption and accessibility improvements during feature development, not a mass rewrite.

## 1. Shared component adoption

Observed JSX usage counts in the supplied source:

| Pattern | Occurrences | Files |
|---|---:|---:|
| Shared `Button` | 217 | 54 |
| Native `<button>` | 179 | 61 |
| Shared `FormField` | 288 | 43 |
| Shared `Input` | 187 | 42 |
| Native `<input>` | 36 | 18 |
| Shared `Select` | 7 | 3 |
| Native `<select>` | 120 | 38 |
| Shared `Textarea` | 8 | 6 |
| Native `<textarea>` | 10 | 9 |
| Shared `Badge` | 79 | 41 |
| Shared `Alert` | 78 | 52 |
| Shared `DataTable` | 23 | 21 |
| Native `<table>` | 42 | 25 |

Counts are source occurrences, not a defect count. They include shared component internals and specialized page markup where applicable.

### Findings

- Shared `Button` is widely adopted, but native buttons remain common. Native buttons in pagination, dialogs, navigation, segmented controls, and specialized interactive widgets are not automatically defects.
- Shared `Input` adoption is comparatively strong. Remaining native inputs are concentrated in the public application form and specialized authentication/assignment flows.
- Native selects remain the largest form-control adoption gap. However, many use `filter-select` styling or are part of complex forms. The shared `Select` currently adds `form-control`, so blind replacement could change styling.
- Native textareas are limited and often embedded in feature-specific forms.
- Native tables are used for complex examination, finance, security, and print-oriented layouts. `DataTable` is a presentation-focused CRUD table and is not a universal replacement.

**Recommendation:** adopt shared controls when creating or materially revising forms. Migrate existing controls only after checking CSS class behavior, native validation, labels, event handlers, and accessibility.

## 2. FormField accessibility and usage

`FormField` currently:
- associates the visible label with the control using `htmlFor`;
- adds `aria-describedby` for hint or error text;
- adds `aria-invalid` when an error exists;
- adds `aria-required` when the field is marked required;
- preserves existing `aria-describedby` values;
- supports a single control, including a control inside a Fragment.

The source shows a consistent pattern of supplying the same ID both to `htmlFor` and the child control.

**Caution:** `aria-required` communicates required state to assistive technology but does not itself enable native browser constraint validation. Do not silently add the native `required` attribute across all existing fields without checking each form's validation and submission behavior.

The component's documented convention—one control per `FormField`—should be followed. Do not wrap multiple independent controls in one `FormField`.

## 3. Inline styles

The audit found **452 JSX inline-style attributes across 48 files**.

Highest concentrations:

| File | Inline styles |
|---|---:|
| `ExamDetailPage.tsx` | 49 |
| `DutyRosterPage.tsx` | 35 |
| `QuestionPapersPage.tsx` | 29 |
| `SecurityPage.tsx` | 28 |
| `ApplicationStatusPage.tsx` | 22 |
| `ExamSetupPage.tsx` | 21 |
| `MyDutiesPage.tsx` | 20 |
| `ResultsPage.tsx` | 20 |
| `FinanceStudentLedgerPage.tsx` | 18 |
| `MarksEntryPage.tsx` | 17 |

These counts do not mean 452 duplicated styles. Some inline styles carry runtime values such as widths, progress, status colors, grid positioning, or print-specific dimensions.

**Recommendation:** classify inline styles before extraction:
1. Dynamic values — retain inline or move to CSS custom properties.
2. Repeated static presentation — extract into a shared or module-owned class.
3. One-off layout adjustments — keep local unless reused.
4. Print-specific styles — keep with the print feature and verify print output.

Prioritize the examination and security screens because they have the highest counts, but inspect the actual values before changing them.

## 4. CSS ownership and selector duplication

The source contains 21 CSS files, including:
- `tokens.css`
- `layout.css`
- `ui.css`
- `module-patterns.css`
- module-owned stylesheets for Academic, Attendance, Examinations, Institution, Applicant Portal, Platform Administration, Platform Configuration, Platform Dashboard, Platform Institutions, Platform Users, Platform Access, Authentication, and Public Portal.

The global import order in `index.css` is documented and follows:
1. tokens and themes;
2. application-shell layout;
3. shared UI/CRUD styles;
4. reusable cross-module patterns.

Feature stylesheets are imported by their owning modules/pages.

The CSS selector scan found a few selectors repeated across stylesheets. Inspection indicates these are intentional responsive or module-specific overrides, not safe-to-delete duplicates:
- `.dashboard-metric-card`: shared base style in `ui.css`, smaller padding override in Platform Dashboard.
- `.institution-details-profile-icon` and `.institution-details-profile-info h2`: shared base patterns with smaller-screen overrides in Platform Institutions.
- `from` and `to`: animation keyframe selectors, not duplicate component rules.

No CSS declarations were changed by this audit.

## 5. Responsive design and theme

The source includes centralized design tokens and theme declarations in `tokens.css`, shell/layout rules in `layout.css`, and responsive overrides within feature stylesheets.

A source-only audit can verify that these styles and breakpoints exist, but cannot prove that the rendered UI has no overflow, clipping, focus visibility problems, or dark-theme contrast regressions.

**Required visual checks before declaring responsive/accessibility completion:**
- Desktop and narrow mobile widths (320px, 375px, 768px, and desktop).
- Light and dark themes.
- Institution navigation and contextual sidebar behavior.
- Long tables and horizontally scrollable data.
- Public application form validation.
- Examination question-paper print preview and printed output.
- Keyboard-only navigation, modal focus entry/trap/return, and visible focus indicators.

## 6. Recommended remaining work

| Priority | Work | Approach |
|---|---|---|
| High | Public application form controls | Review native inputs/selects for consistent labels, errors, and keyboard behavior; migrate only where styling and validation remain equivalent. |
| High | Examination inline styles | Extract repeated static patterns; retain dynamic sizing and print-specific values. |
| Medium | Security page inline styles | Separate reusable status/layout classes from dynamic values. |
| Medium | Native select adoption | Introduce shared `Select` only where `form-control` styling is appropriate; preserve `filter-select` styling otherwise. |
| Medium | Native button adoption | Use shared `Button` for ordinary actions; retain native semantic controls for specialized interactions where appropriate. |
| Medium | Complex tables | Keep semantic native tables where they need grouped headers, custom row structures, print layouts, or specialized interactions. |
| Final | Browser visual and accessibility review | Test real rendered screens; source inspection alone is insufficient. |

## 7. Conclusion

The current design-system foundation is in good shape:
- shared UI and CRUD components are established and widely used;
- CSS ownership is clearer and cross-module `institution.css` coupling has been removed in the supplied snapshot;
- design tokens, layout, shared UI, and reusable module patterns have distinct responsibilities;
- remaining native controls and inline styles are not, by themselves, evidence of architecture failure.

**No broad code patch is recommended from this audit alone.** A mass replacement would risk regressions without a demonstrated benefit. The best next step is targeted remediation, beginning with the Public Application form and the highest-inline-style Examination screens, followed by browser-based visual and keyboard testing.

## Validation boundary

This is a static source audit of the uploaded `src` snapshot. It does not include a local dependency install, TypeScript build, ESLint run, browser automation, or visual regression testing. Continue using the project's local `npm run lint` and `npm run build` after any future code changes.
