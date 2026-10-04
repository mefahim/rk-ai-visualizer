# Phase 3 — WordPress Integration Validation Report

- **Date:** 2026-10-05
- **Branch:** `feat/wordpress-integration-validation`
- **Scope:** Disposable WordPress integration validation only. No real AI provider was called and no production environment was used.

## Environment

| Component | Version / configuration |
|---|---|
| WordPress | 7.1.2, official distribution in a disposable local install |
| PHP | 8.3.6 (CLI and built-in web server) |
| Database | MariaDB 10.11.14 |
| PHP image support | GD enabled |
| Theme | Twenty Twenty-Five |
| Provider | Mock only; Gemini and Hugging Face credentials/API calls were not used |
| Test data | Generated image fixtures and synthetic contact details only |

The temporary WordPress instance was isolated under `/home/ubuntu/wp-disposable`; it was not production hosting.

## Plugin activation and bootstrap

- Deactivation and reactivation through WP-CLI both succeeded.
- The `flooring` and `kitchen` definitions were present in the live registry; the shortcode was registered.
- The plugin served the shortcode test page and its assets after activation.
- WordPress `debug.log` contained no PHP warnings, notices, deprecations, or fatal errors during the final activation and request pass.
- Real hook execution exposed missing imports for REST, renderer, frontend asset, and admin settings callback classes. The bootstrap imports were corrected; the current-source standalone hook-registration regression passes.

## Shortcodes and browser frontend

Both shortcodes were rendered on the same real WordPress page:

- `[rk_ai_visualizer visualizer="flooring"]`
- `[rk_ai_visualizer visualizer="kitchen"]`

Observed results:

- Both definitions rendered through the generic renderer, in their configured field order, without PHP errors.
- Flooring required choices and the normal form-validity path worked.
- Kitchen's custom-instructions field was initially hidden and not required; selecting **Custom** made it visible and required.
- A theme display rule had overridden the HTML `hidden` attribute. A generic `.rkaiviz [hidden]{display:none!important}` rule now preserves conditional hiding.
- Flooring browser generation reached the Mock provider. A 1.5-second browser-only delay showed the loading state; the completed result appeared and the submit control recovered.
- A corrupt upload displayed the server validation error. Replacing it with a valid image and retrying produced a successful result.
- The real lead-unlock dialog opened and accepted synthetic details; it closed after success and the bonus allowance was applied.
- Kitchen generation and conditional behavior were also exercised against the actual REST route.
- A 390 × 844 headless Chromium screenshot showed the forms in a single column without horizontal overflow. No console-breaking JavaScript errors were observed.

## REST contracts

The actual WordPress REST routes were exercised anonymously, without a WordPress nonce, as expected for the plugin's public visualizer endpoints:

| Check | Result |
|---|---|
| Flooring canonical quota | HTTP 200; definition resolved as `flooring`; anonymous visitor cookie issued |
| Kitchen canonical quota | HTTP 200; definition resolved as `kitchen`; independent visitor state |
| Flooring/Kitchen canonical generation | HTTP 200 with Mock image results |
| Existing legacy Flooring quota and generation aliases | HTTP 200 |
| Legacy Flooring lead alias | HTTP 200 |
| Canonical Flooring and Kitchen lead routes | HTTP 200 for valid synthetic leads |
| Canonical Flooring, Kitchen, and legacy status routes with unknown job IDs | HTTP 404 `rk_viz_no_job`, as expected |

The Mock provider completes synchronously, so the pending/terminal lifecycle of a real asynchronous provider was not exercised against WordPress. The standalone suite covers the adapter polling contract with intercepted responses.

## Upload validation

Actual multipart requests were sent through WordPress and the plugin REST routes:

| Fixture | Result |
|---|---|
| Valid JPEG | HTTP 200; Mock generation succeeded |
| Valid PNG | HTTP 200; Mock generation succeeded |
| Valid WebP | HTTP 200; Mock generation succeeded |
| Oversized image | HTTP 413 `rk_viz_too_large` |
| Unsupported text file | HTTP 415 `rk_viz_bad_type` |
| Corrupt PNG fixture | HTTP 415 `rk_viz_bad_type`; rejected cleanly by WordPress MIME/image validation |
| Image below minimum dimensions | HTTP 400 `rk_viz_too_small` |

The invalid-upload batch left the Flooring free-quota value unchanged (1 before and after). Valid generated JPEG, PNG, and WebP output URLs were fetched successfully with their corresponding image MIME types.

## Quota, identity, and refund behavior

- Separate anonymous cookie jars received separate Flooring and Kitchen quota states; each started with the configured two free generations.
- Successful requests consumed allowance. The Kitchen sequence consumed both free requests, accepted a lead, exposed one bonus request, then completed a bonus Mock generation.
- With the test IP cap set to one request, a first malformed generate request reached upload validation and returned HTTP 400; the second request returned HTTP 429 before upload parsing.
- To test reservation refund, the disposable image-output directory was temporarily made unwritable. Generation returned HTTP 502, and the Flooring allowance remained at 2 before and after.
- The forced write failure produced no PHP warning in the final debug log.
- Repeating a lead request with an unchanged persisted transient now succeeds rather than being misreported as a storage failure.

## Lead behavior

- Invalid email: HTTP 400 `rk_viz_lead_invalid`.
- Invalid phone: HTTP 400 `rk_viz_lead_invalid`.
- Valid Flooring and Kitchen leads: HTTP 200.
- Duplicate Kitchen lead using a case-variant email: HTTP 200; the option store retained exactly one matching record.
- The browser Flooring lead-unlock flow also succeeded using synthetic details.
- No real customer data was used.

## Security observations

- The WordPress-safe URL validator rejected loopback HTTPS, non-HTTPS loopback, and URLs containing user information.
- A scan of the rendered page found no provider credential fields or test secrets.
- Only the Mock provider was configured; no Gemini/Hugging Face requests were made.
- Anonymous routes are intentionally public; tested controls include upload validation, visitor quotas, and per-IP throttling rather than logged-in-user authorization.
- Generated images are served from the WordPress uploads directory so the frontend can display them; production operators should apply the documented retention and uploads-directory access expectations.
- The final WordPress debug log was clean after activation, generation, validation errors, lead flows, and the controlled storage failure.

## Issues found and changes made

1. **Bootstrap callback resolution:** the real WordPress path exposed missing namespace imports for the REST, renderer, asset, and settings callback classes. Added the correct imports and a regression test for hook and shortcode registration.
2. **Conditional field visibility:** the active theme's CSS overrode the browser's default hidden behavior. Added a generic scoped `[hidden]` override and a regression test.
3. **Transient no-op semantics:** WordPress may return `false` from `set_transient()` when the requested value is already stored. Engine persistence now confirms that the stored array exactly matches the intended state before reporting failure; a repeated-lead regression covers this.
4. **Expected image-write failure:** an unwritable output directory emitted a PHP warning before the normal error response. The write now suppresses that expected filesystem warning while retaining the existing failure result; the actual WordPress failure/refund path was retested.
5. **Asset cache invalidation:** bumped the plugin version to `0.1.1` so an update invalidates cached stylesheets and delivers the visibility fix.

These fixes are generic; no Kitchen-specific branches were added to the Core, Provider, Quota, Lead, or REST implementation.

## Tests executed and final result

- Standalone suite under PHP 8.3.6 with `E_ALL`: **38 passed, 0 failed** (the original 35 remain passing; three integration regressions were added).
- PHP syntax lint: **26 PHP files passed**.
- JavaScript syntax: `node --check assets/js/visualizer.js` passed.
- PHP 7.4 compatibility pattern scan: no known PHP 8-only syntax constructs matched. This is a static scan, not execution on PHP 7.4.
- `git diff --check`: passed.
- Real disposable WordPress activation, REST, uploads, Mock generation, lead/quota behavior, browser interactions, mobile viewport, and debug-log checks: passed within the boundaries above.

## Failures, limitations, and architecture findings

- **WP-CLI admin-context limitation:** a separate `wp --context=admin` attempt to run WordPress's global admin-menu loader failed inside core `wp-admin/includes/menu.php` (warnings on `foreach()` and a `TypeError` in `uksort()` because that isolated WP-CLI context had no initialized menu globals). This was not reproduced during normal plugin activation, front-end requests, or REST calls. The plugin's real admin settings page was therefore not independently rendered in a browser and remains to be checked in a fully bootstrapped admin session.
- The validation covers one WordPress/PHP combination only; it is not production or cross-version certification.
- PHP 7.4 compatibility was audited statically but not executed on a PHP 7.4 runtime.
- Real Gemini/Hugging Face API calls and WordPress polling of a real asynchronous provider remain intentionally deferred.
- Transient quota updates are not distributed atomic locks; this existing scaling limitation remains for high-volume or multi-server installations.

## Final assessment

The generic plugin architecture completed the tested WordPress activation, rendering, REST, upload, quota, lead, and Mock-generation paths after the integration fixes above. No architecture redesign was needed. The next validation step should be a fully bootstrapped WordPress admin-session check and, only when separately authorized, real-provider integration testing. **Do not treat this report as a production deployment approval.**
