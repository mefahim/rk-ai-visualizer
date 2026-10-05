# Phase 6 — Public Visualizer UI/UX Plan

## Pre-change audit

### Current renderer and schema

`includes/frontend/renderer.php` uses the Definition Registry and loops through each definition's fields, so Flooring and Kitchen already share one renderer. The upload control is a plain required file input; image acceptance is hard-coded to JPG/PNG/WebP, and the renderer does not expose definition upload limits to the browser. Existing field markup covers select, radio, cards, swatches, textarea, text, number, toggle, and slider, but `cards`/`swatches` currently render as plain radios. Defaults are only partially reflected, required selects have no explicit empty choice, labels/IDs can collide when multiple shortcodes appear on one page, and generated/empty/result markup is minimal.

The Definition schema validates `text`, `textarea`, `number`, `select`, `radio`, `cards`, `swatches`, `toggle`, and `slider`. An image-valued option field is not currently supported and is rejected at registration; no existing Flooring or Kitchen field uses one. Both definitions already declare upload constraints, required fields, help/placeholder metadata, and generic conditional rules. Kitchen's custom-instructions textarea is conditional and conditionally required.

### JavaScript and CSS states

`assets/js/visualizer.js` already refreshes quota, blocks duplicate generation submissions, preserves form values while requests run, handles synchronous completion and asynchronous polling, opens the existing lead dialog for the `needs_lead` quota phase, and displays success/error output. It creates a local preview URL when a file is selected. It does not yet provide drag/drop, client-side MIME/size/dimension/corrupt-image checks, replace/remove controls, a distinct retry/reset flow, or resilient loading/status copy. It currently renders arbitrary API `message` text directly.

`assets/css/visualizer.css` is very small. It has a two-column layout and a few visual rules, but the upload, option controls, preview, result, and dialog are largely browser defaults. Rules are generally rooted at `.rkaiviz`, but the new stylesheet will use a stronger `.rk-ai-visualizer` root for all public component selectors.

### REST and engine contract

No API or engine change is planned. The existing generic REST routes remain:

- `GET /wp-json/rk-ai/v1/visualizer/{slug}/quota`
- `POST /wp-json/rk-ai/v1/visualizer/{slug}/generate` with multipart `image` and JSON-encoded `options`
- `GET /wp-json/rk-ai/v1/visualizer/{slug}/status?job={id}`
- `POST /wp-json/rk-ai/v1/visualizer/{slug}/lead` with JSON contact fields

Generation returns `success` with `imageUrl`, or `pending` with `job`; quota exhaustion can return `kind=quota_denied` and `phase=needs_lead`. REST errors use WordPress error envelopes. The engine already returns user-facing messages for upload/validation/rate/quota/timeout/provider failures; the client will map known codes/phases to safe UI copy instead of exposing arbitrary upstream messages.

## Implementation plan

1. Create `feat/public-visualizer-ui` from `origin/main`, keeping the open Phase 5 admin PR separate and unmerged.
2. Restructure only the generic shortcode HTML into a branded, responsive workspace: upload step, definition-driven options, preview/result surface, truthful status copy, and the existing lead dialog/CTA.
3. Add generic field rendering for the schema-supported types, including correct labels/defaults/help/required/conditional states. Extend the existing choice schema only as needed for `image` cards: retain scalar option values/labels and require a safe per-option image URL; add no definition-specific branches.
4. Replace the minimal CSS with strongly namespaced responsive styles under `.rk-ai-visualizer`, including clear focus states and touch-sized controls.
5. Extend the existing vanilla JS with drop/browse, client feedback consistent with server upload rules, replace/remove/reset/retry actions, accessible status/error announcements, and safe message mapping. Keep the current REST routes and request payloads unchanged.
6. Add focused tests for renderer output, upload/conditional states, generation/result/error behavior, and style isolation/responsive hooks; then run PHP lint, core tests, admin tests present on `main`, frontend tests, and REST contract regressions.
7. Write an implementation report, review the diff, commit, push the feature branch, and open an unmerged PR against `main`.

## Guardrails

No provider, engine, quota, lead-storage, REST-contract, or definition-architecture redesign. No Admin Visualizer Builder, analytics, generation history, or new dependency. Image choices are a narrow schema extension demanded by the UI specification: option values remain the same scalar keys consumed by current validation and prompt mappings; existing Flooring and Kitchen definitions remain unchanged.
