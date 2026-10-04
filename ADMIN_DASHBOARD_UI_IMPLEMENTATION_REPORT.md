# Phase 5 — Admin Dashboard UI Implementation Report

## Summary

Added a dedicated RK AI Visualizer administration workspace inside WordPress, replacing the plugin's former placement under **Settings → RK AI Visualizer** as the primary user-facing entry point. The workspace has Dashboard, Visualizers, Leads, and Settings screens. Existing WordPress Settings API registration remains in place for persistence and sanitization.

Implementation branch: `feat/admin-dashboard-ui`  
Pull request: [Phase 5 — Admin Dashboard UI](https://github.com/mefahim/rk-ai-visualizer/pull/5), from `feat/admin-dashboard-ui` to `main` (**open; not merged**).

## RK-React-Builder reference findings

Reviewed the public [RK-React-Builder repository](https://github.com/rakib6564/RK-React-Builder), especially `client/src/components/dashboard/Dashboard.tsx` and `client/src/App.tsx`.

Architecture/layout observations used as reference only:

- A single application shell separates navigation from the main content area.
- A central dashboard owns a clear navigation model and selects independent content sections.
- The desktop interface uses a persistent sidebar, while compact navigation is provided for smaller screens; secondary destinations can be grouped when space is constrained.
- A dashboard overview acts as the operational entry point rather than presenting configuration controls as the first screen.

No code, exact visuals, colors, or branding were copied. RK AI Visualizer uses its own dark-teal/soft-green visual palette, wordmark, labels, and navigation styling.

## New dashboard information architecture

```text
RK AI Visualizer
├── Dashboard
├── Visualizers
│   └── Visualizer detail
├── Leads
└── Settings
```

The Dashboard is the primary plugin admin entry point and summarizes active/configured visualizers, the global free-generation allowance, lead count, selected provider, and the plugin-wide enabled/paused state. It also exposes quick actions, a visualizer status/context panel, and recent leads. It does not contain a giant settings form.

## Navigation structure

- **Desktop:** plugin-specific sidebar inside the WordPress admin content area, with Dashboard, Visualizers, Leads, and Settings destinations.
- **Mobile/tablet:** the sidebar becomes a compact horizontally scrollable navigation row; stat cards and visualizer cards collapse to narrower layouts, and data tables remain horizontally scrollable.
- WordPress's Settings API remains the backing persistence interface; the user-facing admin flow is under the plugin's own menu.

## Screen structure

### Dashboard

- Four summary cards: active visualizers, configured visualizers, global free generations, and leads captured.
- System-status panel with current provider and global availability.
- Quick actions to Visualizers, Leads, and global Settings.
- Latest leads from the existing lead store.

### Visualizers

- Data-driven catalog from the existing Definition Registry; no `flooring`/`kitchen` branches in dashboard logic.
- Each entry shows name, slug, built-in/custom type, current global status, shortcode, and management/preview actions.
- Preview links are enabled only when a published post/page containing that visualizer's shortcode can be found.
- Visualizer detail pages provide Overview, Appearance, Fields, Prompt, Usage, and Advanced sections. Definition-backed fields, prompts, upload rules, and quota defaults are presented read-only; this phase does not introduce a definition editor.

### Leads

- Lead table displays name, email, phone, visualizer/source, creation date, and Delete action.
- Uses the existing `rk_ai_visualizer_leads` option and `Leads::all()` access path; no storage migration was added.
- Delete URLs carry an opaque HMAC reference rather than exposing an email address and are protected with a WordPress nonce and the `manage_options` capability.

### Global Settings

- Provider and availability settings, provider credentials, and shared visitor quotas are grouped and labeled as global.
- Uses the existing `rk_ai_visualizer_settings` option and WordPress Settings API sanitization; secrets remain write-only in the form.
- Definition-specific display/prompt/field values are not duplicated here.

## Global vs. visualizer-specific settings

| Scope | UI treatment | Backing source |
|---|---|---|
| Provider selection and credentials | Editable under Settings | Existing `rk_ai_visualizer_settings` option and provider configuration path |
| Plugin-wide availability | Editable under Settings | Existing `enabled` value in `rk_ai_visualizer_settings` |
| Shared quota/rate/timeout settings | Editable under Settings and labeled global | Existing settings option |
| Definition name, slug, copy, fields, prompt, upload constraints, and quota defaults | Read-only in visualizer detail | Existing registered Definition objects |
| Lead records | View/delete under Leads | Existing `Leads` storage option/API |

Built-in/custom classification is generic Definition metadata. Existing built-in definitions declare their type; unmarked extension definitions default to `custom`.

## Responsive strategy

- CSS Grid provides a desktop sidebar/content shell and multi-column dashboard cards.
- At the WordPress mobile breakpoint the sidebar becomes a compact horizontal navigation row with horizontal overflow rather than forcing the host WordPress navigation to wrap the plugin UI.
- At narrow widths dashboard and visualizer cards become single-column, and tables scroll horizontally.
- Focus-visible outlines, semantic navigation labels, current-page states, and accessible status text are included.

## Files changed

- `includes/admin/dashboard.php` — dashboard shell, navigation, dashboard summary, visualizer catalog/detail screens, lead list/delete handler, and settings view.
- `includes/admin/settings.php` — retains sanitization/registration and Settings API storage; organizes the existing form into global sections.
- `includes/bootstrap.php` — loads the new admin dashboard class.
- `includes/definitions/definition.php` — validates generic `built-in`/`custom` metadata.
- `includes/definitions/flooring.php` — declares built-in type metadata.
- `includes/definitions/kitchen.php` — declares built-in type metadata.
- `assets/css/admin.css` — independent responsive admin styles.
- `tests/admin-dashboard.test.php` — admin screen and responsive CSS tests.
- `.github/workflows/php.yml` — runs the new admin regression suite in CI.
- `ADMIN_DASHBOARD_UI_IMPLEMENTATION_REPORT.md` — this report.

## Tests

Executed locally:

- PHP syntax check across all plugin PHP files — passed.
- Existing PHP core regression suite — **41 tests, 0 failures**.
- New admin dashboard regression suite — **7 tests, 0 failures**.
- `node --check assets/js/visualizer.js` — passed.
- `node tests/frontend.test.js` — passed.

The admin tests exercise menu registration, dashboard summaries, visualizer listing and preview lookup, detail sections, Settings API form grouping, nonce-protected lead-delete references, built-in metadata, and mobile navigation styles.

## Screenshots

No screenshots were captured. This sandbox does not have a live WordPress site attached for visual browser validation; the PHP screen renderers were exercised using the repository's WordPress test fakes.

## Limitations and explicit scope boundaries

- The existing system exposes one plugin-wide `enabled` flag, not per-visualizer availability. The catalog accurately reflects that shared state and links status/configuration to Global Settings; it intentionally does not invent per-item on/off controls or change public rendering, REST, engine, provider, or quota behavior.
- Preview requires a published page/post containing the visualizer shortcode; an unavailable preview is shown as such.
- Detail screens are read-only because a safe dynamic Definition Builder is explicitly out of scope.
- Generation analytics/history do not exist in current storage and were not added; Usage shows only existing lead counts.
- Per-visualizer quota values remain definition metadata/read-only; shared quota controls remain global and are not duplicated.
- WordPress live integration and browser screenshots have not been run in this sandbox. CI has been updated, but its remote GitHub Actions result will be available only after the pull request is opened.
- The pull request is not to be merged.
