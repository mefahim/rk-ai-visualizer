# RK AI Visualizer

A standalone, definition-driven WordPress plugin foundation for AI image visualization. Phase 1 shipped the reusable engine and Flooring Visualizer; the Kitchen Visualizer is a second definition used to validate the same architecture. Visualizer-specific fields and prompt rules are definition data, not branches in the engine or renderer.

## Requirements and installation

- WordPress 6.0+; PHP 7.4+.
- Copy this directory into `wp-content/plugins/rk-ai-visualizer/` and activate **RK AI Visualizer**.
- In **Settings → RK AI Visualizer**, enable the plugin and select a provider. For local smoke tests choose **Mock**; it stores and returns the uploaded image without using an AI service.
- Insert `[rk_ai_visualizer visualizer="flooring"]` or `[rk_ai_visualizer visualizer="kitchen"]` into a page. Optional shortcode attributes: `cities="Peoria\nPeoria Heights"`, `submit_label="Create visualization"`, `cta_label="Talk with us"`, and `cta_url="https://example.com/contact"`.

## Providers

- **Mock**: synchronous local image echo, useful for tests and UI verification.
- **Gemini**: synchronous image edit; key comes from `RK_AIVIZ_GEMINI_KEY`, `GEMINI_API_KEY`, or the protected settings option. The API key is sent only in a request header.
- **Hugging Face**: queued FLUX Kontext image edit; key comes from `RK_AIVIZ_HF_TOKEN`, `HF_TOKEN`, or settings. Queue URLs are validated and translated only from `fal.run` hosts.
- **Custom**: JSON backend using an administrator-configured HTTPS endpoint; supports HTTPS image URLs, base64/data-URI images, and asynchronous `statusUrl` responses. Polling is restricted to the configured backend host.

Provider input is normalized to `prompt`, `image_path`, `mime_type`, `metadata`, and validated `options`; provider output is normalized to `completed` + `image_url`, `pending` + `job`, or `WP_Error`.

## REST API

Canonical definition-aware endpoints (the definition slug defaults to `flooring` in the shortcode; `kitchen` uses the same routes):

- `GET /wp-json/rk-ai/v1/visualizer/{visualizer}/quota`
- `POST /wp-json/rk-ai/v1/visualizer/{visualizer}/generate` — multipart `image` and JSON `options`
- `GET /wp-json/rk-ai/v1/visualizer/{visualizer}/status?job={id}`
- `POST /wp-json/rk-ai/v1/visualizer/{visualizer}/lead` — JSON `name`, `email`, optional `phone`

The legacy conceptual paths `/wp-json/rk/v1/visualizer/{quota,generate,status,lead}` remain registered and target Flooring. Generation/status/quota responses are marked `Cache-Control: no-store, private`; async jobs are bound to the HttpOnly visitor cookie and refund reserved quota after provider failure/timeout.

## Extension points

Register a `RK\AIVisualizer\Definitions\Definition` with `Registry::instance()->register(...)` during plugin bootstrap. A definition owns slug/name, upload constraints, generic field descriptions/options, prompt maps/rules, CTA/copy, and quota defaults. Flooring and Kitchen use the same validator, prompt composer, engine, provider interface, REST routes, and renderer. Supported field types are text, textarea, number, select, radio, cards, swatches, image, toggle, and slider. Image-choice fields use static `options` plus an `images` map keyed by the same scalar option values; thumbnail URLs must be HTTP(S) without credentials or site-root-relative paths. Validation and prompt mappings continue to receive the scalar option key. The source photo remains an engine-level upload, separate from visual option fields. Unsupported field types and malformed definitions fail at registration rather than falling through to generic text rendering.

See [ARCHITECTURE.md](ARCHITECTURE.md) for boundaries and flow.

## Security and data

- Uploads are verified from file bytes/MIME and dimensions; client-side checks are not trusted.
- Provider requests use HTTPS plus WordPress safe-URL validation, disable redirects, and cap response bodies at 20 MiB. Custom async status URLs must match the configured HTTPS host and effective port; Hugging Face polling URLs are revalidated against the fixed router host/path.
- API credentials are never emitted in frontend markup or provider URLs. Prefer constants/environment variables for production secrets.
- Lead data is stored in the `rk_ai_visualizer_leads` WordPress option with email deduplication and a 500-record cap. Restrict database access and follow your privacy/retention obligations.
- The plugin uses a random, HttpOnly `rk_ai_viz` first-party cookie (365-day expiry) to associate anonymous quota state. Hourly IP rate-limit keys are HMACed with the WordPress authentication salt and expire after one hour; forwarded client-IP headers are deliberately not trusted.
- Generated images are validated before writing to a random filename in `uploads/rk-ai-visualizer/`; files older than seven days are cleaned during generation.
- REST is public because it serves anonymous visitors; upload checks, visitor quota, per-IP limits, strict input validation, non-cacheable responses, and job ownership constrain use. Site operators should layer their WAF/rate limits as appropriate.

## Tests

The PHPUnit-free test runner uses WordPress fakes and intercepted HTTP responses; it does not call real AI providers. Frontend request-state regressions run under Node:

```sh
php tests/run.php
node tests/frontend.test.js
```

Disposable WordPress activation, shortcode rendering, public REST, upload, quota, lead, Mock generation, and browser checks were completed in Phase 3 (the detailed report remains in the source repository and is not included in the install ZIP). That integration pass used WordPress 7.1.2 and PHP 8.3.6; it is not production or cross-version certification. Real Gemini/Hugging Face calls remain intentionally deferred. The PHP 7.4 compatibility review is static; the standalone code-level suite runs under PHP 8.3.

## Distribution

The installable release ZIP contains a single top-level `rk-ai-visualizer/` directory with the plugin entrypoint, runtime `includes/`, frontend `assets/`, `README.md`, `ARCHITECTURE.md`, and the complete official GPL version 2 text in `LICENSE`. The plugin header declares **GPL-2.0-or-later**. Tests, CI configuration, Git metadata, and implementation reports are excluded from the ZIP; they remain in the source repository.
