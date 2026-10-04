# Phase 1.5 — Code Hardening Report

**Branch:** `feat/phase1-hardening`  
**Scope:** Internal code, schema, lifecycle, and test hardening only. This phase did not perform a real WordPress activation, live REST integration, or real AI-provider request.

## Issues found

- The injectable `Engine` registry used a nullable parameter; the signature needed to express nullability explicitly for modern PHP compatibility.
- Definitions accepted incomplete or inconsistent schemas: unsupported field types, duplicate/unsafe field IDs, malformed options, invalid numeric/upload/quota bounds, invalid regular expressions, and conditions that referenced unknown or later fields.
- Core generation parsed uploads before checking quota/IP limits, spending work on requests that could already be rejected. Slugs were not consistently canonicalized at every engine entry point.
- Stored async job data and REST polling were not fully constrained to a valid phase/shape and the requested definition slug. Persistence failures could leave quota reservations inconsistent.
- Provider URL validation was uneven. Custom polling compared host but not effective port; Hugging Face polling did not revalidate both stored URLs; URL checks did not consistently reject private/local destinations or unsafe completion URLs. Provider responses had no explicit body-size cap, and polling adapters could interpret non-2xx bodies as valid states.
- Quota input/state values were trusted too much, and lead storage ignored option-write failures. Lead phone validation was permissive, raw IP was retained with lead records, and failed lead persistence could otherwise unlock a bonus.
- Generic boolean/numeric field conditions were not normalized consistently between backend validation and frontend visibility behavior.

## Fixes made

- Made the registry constructor parameter explicitly nullable (`?Registry $registry = null`) and added a reflection regression check.
- Hardened `Definition` validation for ordered field lists, unique safe IDs, implemented field families, option structures, supported options sources, numeric constraints, regex syntax, condition references/order, upload MIME/size/dimension rules, and quota ranges. Image upload remains a core input; an image-valued dynamic option field remains intentionally unsupported.
- Canonicalized definition slugs across quota, generation, and lead operations; moved quota/IP checks ahead of upload parsing; validated async record shape, owner, phase, and definition association; failed closed when reservations or job storage could not be written and refunded failed jobs where possible.
- Normalized quota settings/state defensively, clamped configuration ranges, preserved rolling cooldown state on refund, and made lead-bonus messaging reflect configured remaining bonus uses.
- Centralized public HTTPS checks through WordPress `wp_http_validate_url()` for remote provider URLs; disabled redirects, retained `reject_unsafe_urls`, and capped provider response bodies at **20 MiB**. Custom async URLs now require exact host and effective-port equality; Hugging Face polling URLs must match the fixed router host/path; remote completion URLs are checked before being returned. Poll adapters reject non-2xx status/result responses. Unreadable upload bytes fail before provider submission.
- Improved lead validation and durability: phone syntax/digit count is checked, generation association length is capped, raw IP is no longer stored with leads, option-write failures return an error, and the engine does not unlock a lead bonus when lead persistence fails.
- Normalized boolean/numeric conditional comparisons and kept frontend conditions/requiredness aligned with those values.
- Updated README, architecture notes, and the Phase 1 implementation report to describe the hardened contracts and remaining validation boundary.

## Tests added or updated

The self-contained suite uses WordPress stubs and fake/intercepted HTTP responses; it makes **no external provider calls**. Regression coverage now includes:

- Explicit constructor nullability and definition-schema rejection cases.
- Numeric clamping, toggle coercion, and boolean conditional visibility/requiredness.
- Loopback/private URL rejection, credential/non-HTTPS URL rejection, provider redirect/body-size/safe-URL arguments, custom status-port mismatch, and tampered Hugging Face result URLs.
- Async status bound to the requested visualizer, job-persistence failure refunds, quota-state persistence failure, and rolling cooldown restoration.
- Lead phone validation, raw-IP omission, and lead-option write failure.
- Rate limiting before upload parsing and rejection of non-2xx async provider responses.

## Final test result

- **PHP syntax:** all plugin and test PHP files pass `php -l` under PHP 8.3.
- **PHP runtime suite:** **27 tests, 0 failures**, run with `E_ALL` and display errors enabled; no warnings/deprecations were emitted.
- **JavaScript syntax:** `node --check assets/js/visualizer.js` passes.
- **Diff hygiene:** `git diff --check` passes.
- **PHP 7.4 audit:** source was statically scanned for PHP 8-only syntax and checked for nullable typed parameters. No disallowed syntax was found; the constructor has an explicit nullable type. An actual PHP 7.4 runtime was not available, so this is not a runtime certification.

## Known limitations

- Real WordPress activation/admin/REST integration was intentionally deferred. The test harness stubs WordPress behavior and cannot prove compatibility with a live WordPress installation or its actual HTTP stack.
- Gemini and Hugging Face API calls were intentionally deferred; model availability, provider payload schemas, credentials, queue timing, costs, and real response formats remain unverified. Custom backend interoperability also needs an agreed real backend contract.
- Anonymous quota identity depends on a browser cookie and IP rate bucket, so it is not account-bound and can be reset or shared. Transient read/modify/write sequences are not atomic across workers, and async completion/refund is not a distributed transaction; higher-volume use needs transactional storage/locking.
- Leads are stored in a capped WordPress option without a retention/export/deletion workflow. Deployment owners must set appropriate privacy, access, retention, and backup controls.
- WordPress safe-URL validation, redirect disabling, HTTPS requirements, and provider host/path checks reduce SSRF exposure, but code-level tests with a stub do not establish protection against every DNS-rebinding or deployment-network edge case.
- PHP 7.4 was reviewed statically but not executed. Only PHP 8.3 lint and tests were run in this environment.

## Recommendation for the next phase

Run a **separate, isolated integration-verification phase** before feature expansion: activate on a disposable supported WordPress installation; verify admin settings and REST/async/lead flows using the mock provider first; then test Gemini and Hugging Face individually with controlled credentials and spending limits. Record any integration changes and rerun this code-level suite. Do not start Kitchen or Phase 2 until that verification is reviewed and explicitly approved.
