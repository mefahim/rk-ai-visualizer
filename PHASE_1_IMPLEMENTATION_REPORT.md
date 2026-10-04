# Phase 1 Implementation Report

## Summary

Implemented the initial standalone RK AI Visualizer WordPress plugin as a definition-driven core engine with the Flooring Visualizer definition. The RK-React-Builder reference was inspected read-only for source flooring option values/labels, prompt wording, image validation, free/bonus/cooldown quota behavior, visitor ownership, lead deduplication, provider contracts, and async polling/refunds. No files in the reference repository were changed.

## Implemented

- Plugin entry/bootstrap and WordPress shortcode integration.
- Definition object and registry; Flooring definition preserves the source option keys/labels for room, project, style, wood, direction, finish and sheen, with custom description, optional city and square footage.
- Generic upload validation: file readability, size, MIME, width and height; generic definition-driven field checks and conditional field handling.
- Generic prompt composer with Flooring-owned prompt rule data and preservation instructions.
- Generic visitor quotas: free, lead bonus, rolling cooldown, IP/hour limit, reservation, async job state, failure and timeout refund.
- Lead validation, normalized email deduplication, capped storage, and visualizer/generation association.
- Secure image storage with supported-image verification, random filenames, upload subdirectory, and seven-day cleanup.
- Provider interface and adapters for Mock, Gemini, Hugging Face queued generation, and configurable Custom HTTPS backends.
- Canonical definition-aware REST routes plus legacy Flooring endpoint aliases.
- Definition-driven shortcode renderer with field loops, city choices, preview, quota, lead modal, conditional fields, and polling JavaScript.
- Capability-protected settings page with masked/saved secrets and bounded quota/provider settings.
- Developer README, architecture notes, and independent fake-based test runner.

## Architecture

The runtime layers are `Definitions → Core Engine → Providers`, with REST and a generic frontend renderer as adapters. Definitions own fields, labels, options, prompt rules, visualizer copy/CTA, upload constraints and quota defaults. The engine owns validation orchestration, quotas, lifecycle, polling/refunds, storage and leads. Providers consume normalized input (`prompt`, `image_path`, `mime_type`, `metadata`, `options`) and return normalized completion/job results. Detailed boundaries and lifecycle are in `ARCHITECTURE.md`.

## Files created/changed

- Plugin root: `rk-ai-visualizer.php`, `.gitignore`, `LICENSE`, `README.md`, `ARCHITECTURE.md`, this report.
- Bootstrap: `includes/bootstrap.php`.
- Core: errors, validation, prompt, quota, storage, leads, engine.
- Definitions: definition contract, registry, Flooring definition.
- Providers: interface, HTTP helper, Mock, Gemini, Hugging Face, Custom, factory.
- REST: route registration/dispatch.
- Frontend: definition-loop renderer, asset registration, generic JS/CSS.
- Admin: provider/quota settings.
- Tests: faked WordPress bootstrap and `tests/run.php`.

## API endpoints

- `GET /wp-json/rk-ai/v1/visualizer/{slug}/quota`
- `POST /wp-json/rk-ai/v1/visualizer/{slug}/generate`
- `GET /wp-json/rk-ai/v1/visualizer/{slug}/status?job=...`
- `POST /wp-json/rk-ai/v1/visualizer/{slug}/lead`
- Legacy routes under `/wp-json/rk/v1/visualizer/{quota,generate,status,lead}` resolve to Flooring.

## Provider behavior

- Mock echoes the validated uploaded image to plugin storage (no external request).
- Gemini posts the photo inline with prompt, uses an API-key header, and stores its image response.
- Hugging Face posts normalized prompt/image to the queue and polls only validated `fal.run`→router paths.
- Custom sends `{prompt, image, mimeType, options}`, accepts HTTPS image URLs or supported encoded images, and restricts async polling to the configured HTTPS host.
- Provider errors are normalized; initial failures and async poll failures refund quota.

## Tests and verification

The test runner covers definition registration/schema/options, image MIME/size/dimensions, field validation, prompt output, quota/refund/persistence failures, lead normalization/deduplication/storage failures, mock provider, generic frontend markup, engine generation/quota, async polling/ownership, custom status URL restrictions, SSRF cases, response caps, and REST route registration. Tests use local fakes and intercepted HTTP responses only. **PHP 8.3 lint passed for every PHP file; the independent suite passes 27 tests with 0 failures under `E_ALL`.** PHP 7.4 compatibility is statically audited; an actual PHP 7.4 runtime is not installed. No live WordPress integration or real-provider test was performed.

## Known limitations

- A live WordPress instance is not available in the execution environment; plugin activation and actual REST routing still require a WordPress integration check before release.
- Provider responses that return externally hosted images are surfaced as HTTPS URLs; the plugin does not proxy those remote files into local storage in Phase 1.
- Leads are stored in a WordPress option but no admin lead browser/export/delete interface or email notifications are included yet.
- Public anonymous REST operations remain susceptible to distributed abuse beyond the per-IP cap; production sites should add WAF/hosting rate limits.
- WordPress transients do not provide a distributed atomic reservation lock under concurrent high-volume requests.
- The settings page provides one global provider configuration; per-definition provider selection and advanced admin definition editing are out of scope.

## Phase 2

1. Run PHP lint/tests on supported PHP versions and exercise activation/REST in WordPress integration tests.
2. Add privacy retention controls and an admin leads list/export/delete workflow.
3. Add provider retry policy, queue cleanup, and robust idempotent reservation storage.
4. Verify the modern Hugging Face API contract against the intended production account/model.
5. Add a second definition (e.g. Kitchen) to prove reusable frontend/prompt/validation behavior.
6. Add admin definition editing, analytics and licensing only after validated product requirements.
