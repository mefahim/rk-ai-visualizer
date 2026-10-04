# Phase 4 — Production Hardening & Packaging Report

- **Date:** 2026-10-05
- **Branch:** `feat/production-hardening`
- **Plugin version:** `0.1.2`
- **Base:** `main` at the merge of Phase 3 PR #3
- **Purpose:** Production-readiness review and clean install-package preparation; no new visualizer features or architecture expansion.

## Audit scope

Reviewed the plugin entry point, Core, Definitions, Providers, REST, frontend assets, admin settings, storage, documentation, CI workflow, test harness and distribution contents for PHP errors and compatibility, unsafe input handling, output escaping, REST errors, upload/filesystem behavior, secret handling, nonce/capability use, provider boundaries and definition-specific coupling.

Phase 4 did **not** make real Gemini or Hugging Face calls and did **not** perform additional WordPress admin testing. The already completed Phase 3 disposable WordPress integration report was reviewed as prior evidence; that local integration used Mock only and is not repeated or represented as production certification.

## Issues found and fixes made

1. **Malformed settings and credential values:** Hardened the settings sanitizer against array-shaped fields and invalid scalar values, retained saved credentials when secret inputs are left blank, honored explicit secret-clearing controls, sanitized stored credentials, and reject credentials longer than 4096 bytes. Invalid provider, model, custom-header, URL, and quota inputs resolve to constrained values. Admin settings remain behind `manage_options`; secret inputs are blank when rendered.
2. **Defensive request/config handling:** Tightened type checks around visitor cookies, remote-IP values, upload temporary paths, quota configuration and persisted quota-state fields. Quota reservations continue to fail closed on storage errors; invalid async provider results are rejected and reservation rollback is attempted.
3. **Provider response parsing:** The shared HTTP adapter now validates response shape and body type and enforces the 20 MiB response cap even on intercepted responses. Non-2xx poll results and malformed status/result nesting are rejected instead of producing PHP warnings or being interpreted as indefinitely pending. Gemini, Hugging Face and custom-provider nested image/candidate parsing now checks types before indexing. These checks use fake/intercepted responses only; no real provider was contacted.
4. **Portable generated-file cleanup:** Replaced brace-expansion globbing with portable `glob()` plus a strict generated-image extension allowlist. Cleanup tolerates failed or malformed upload-directory responses and only attempts to remove old regular image files.
5. **Frontend state recovery:** Extended the Node regression to assert duplicate generation submissions are locked and that loading/submit state recovers after success, an HTTP error and a network rejection. Error content continues to be rendered with `textContent`, and image results are assigned as element properties rather than interpolated into HTML.
6. **CI workflow:** Removed the Composer validation step because this plugin has no `composer.json`; that step caused PR #3's unrelated CI failure. The workflow now runs PHP lint and tests on PHP 7.4 and 8.3 and runs JavaScript syntax and frontend tests under Node 22.
7. **Release metadata and documentation:** Bumped the plugin header and asset version consistently to `0.1.2`, added the complete official GPL version 2 license text, clarified the declared `GPL-2.0-or-later` license, and made the README package references self-contained.

No new visualizers or provider features were added. No definition-specific branch was added to the generic Core, Provider, REST or rendering logic. Flooring remains only the existing default/legacy target at the shortcode/route boundary; Kitchen remains a definition.

## Security findings

- **Public routes are intentional:** Anonymous quota, generation, status and lead routes are public for the visitor-facing tool. They do not authorize privileged WordPress actions. Controls include strict image/type/size/dimension and field validation, per-visitor quota, per-IP throttling, non-cacheable responses and async job ownership checks. Admin configuration uses the WordPress capability/settings API and nonce-bearing settings form. A public generation route is not a substitute for WAF/rate limits; cookie/IP-based limits can be bypassed by determined clients.
- **SSRF protections:** Provider requests require HTTPS and WordPress safe-URL validation, disable redirects, set `reject_unsafe_urls`, and cap response bodies. URLs with credentials are rejected. Custom async polling is restricted to the configured host and effective port; Hugging Face queue/result URLs are restricted to the fixed router host and expected path. DNS or infrastructure changes and proxy/network policy remain deployment concerns; this audit does not certify the site's outbound network controls.
- **Secrets:** Provider credentials are resolved server-side from constants/environment/settings, are sent in request headers where applicable, and are not printed in frontend markup. Admin password fields do not display stored values. The custom provider URL is administrator-configured and must be HTTPS/public-safe.
- **Uploads and generated files:** Upload content is checked from bytes/MIME and dimensions; storage uses random names and validates generated image bytes. Old generated JPG/PNG/WebP files are eligible for cleanup after seven days. Generated assets are intentionally served from the WordPress uploads URL and therefore are publicly fetchable; site operators should consider privacy and retention needs.
- **Identity and quota:** Visitor IDs are random and stored in an HttpOnly cookie. IP rate-limit records are keyed by an HMAC using the WordPress authentication salt rather than storing raw IPs. Quota/job state is stored in WordPress transients/options, not a transactional multi-server store.
- **Output escaping:** Admin labels, attributes, values and options use WordPress escaping helpers; frontend user/provider-facing messages use `textContent`; JSON and shortcode attributes follow WordPress serialization/escaping paths.

## Compatibility findings

- Local PHP runtime: **PHP 8.3.6**. All 26 PHP files passed `php -l`; the suite ran with `E_ALL` and no warnings/deprecations were reported.
- The code-level audit found no use of known post-PHP-7.4 syntax/features in runtime PHP files. PHP 7.4 is not installed in the sandbox, but the GitHub Actions matrix for PR #4 completed successfully on both PHP 7.4 and 8.3 (run `37242759235`), including PHP lint, the standalone suite and frontend checks.
- The prior Phase 3 report records a disposable WordPress 7.1.2 / PHP 8.3.6 activation, route, upload, Mock-provider, lead/quota and browser pass. That is a single-version integration observation, not broad compatibility certification. The Phase 3 report also records an isolated WP-CLI admin-menu bootstrap limitation; no admin screen was re-tested in Phase 4.

## Tests and checks

- PHP standalone suite (`php -d error_reporting=E_ALL -d display_errors=1 tests/run.php`): **41 passed, 0 failed**.
- PHP syntax lint: **26 files passed**.
- Frontend syntax (`node --check assets/js/visualizer.js`): **passed**.
- Frontend regression (`node tests/frontend.test.js`): **passed**; covers duplicate-submit prevention and UI recovery for success, HTTP error and network error.
- `git diff --check`: **passed** at the validation checkpoint.
- GitHub Actions run `37242759235`: **passed** for both PHP 7.4 and PHP 8.3 matrix jobs.
- Existing disposable WordPress integration checks: see the existing Phase 3 report in the source repository. No additional WordPress admin testing was performed.
- Gemini/Hugging Face external API calls: **not performed**, as required.

## Packaging status

- Plugin entry-point metadata and asset cache version agree at **0.1.2**; minimum versions remain WordPress 6.0 and PHP 7.4.
- The release archive is prepared with exactly one top-level `rk-ai-visualizer/` directory and contains the entry point, runtime `includes/`, frontend `assets/`, `README.md`, `ARCHITECTURE.md` and `LICENSE`.
- The ZIP excludes `.git`, workflow/CI files, tests, phase reports and other development artifacts. Archive integrity and contents are checked before delivery.
- The complete official GNU GPL version 2 license text is included. The plugin metadata declares `GPL-2.0-or-later`.

## Remaining known limitations

- PHP 7.4 is not available as a local sandbox runtime; compatibility was executed by the successful PHP 7.4 CI job, but not in a live WordPress/PHP 7.4 installation.
- No real Gemini/Hugging Face APIs, no additional live WordPress admin screen tests and no production deployment were run.
- Quota/job read-modify-write operations in transients are not distributed atomic locks; high-volume or multi-server use may require transactional persistence and stronger abuse controls.
- Publicly served output files, option-stored lead data, and site-specific retention/privacy duties remain operational responsibilities.
- The previous WordPress integration covered one disposable WordPress/PHP setup and Mock provider only; it is not cross-version or production certification.

## Final assessment

Phase 4 identified and addressed concrete malformed-input, malformed-provider-response, portability, frontend-regression, CI and release-packaging issues without expanding product scope. Local validation and both CI PHP-version jobs pass. The branch is ready for the requested review; it remains open and must not be merged until reviewed by the repository owner.
