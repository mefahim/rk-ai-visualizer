# Architecture

## Layers

- **Definition** (`includes/definitions/`): business-facing data: identity, upload constraints, field schema/options, prompt configuration, copy/CTA and quota defaults. Flooring is the Phase 1 implementation; Kitchen is a second definition validating reuse. Definitions contain no execution logic.
- **Core** (`includes/core/`): reusable engine, validation, prompt composition, quota reservations/refunds, lead persistence, image storage/cleanup, and normalized errors. Core methods receive a definition and do not branch on a particular visualizer slug or field identifier.
- **Provider** (`includes/providers/`): `ProviderInterface` maps normalized inputs to normalized results. Gemini is synchronous; Hugging Face and custom backends can return jobs; mock is synchronous. Provider-specific payloads and response formats stay in adapter classes.
- **REST** (`includes/rest/`): REST paths resolve a slug and delegate to the engine. Legacy non-definition paths default to Flooring.
- **Frontend** (`includes/frontend/`, `assets/`): the shortcode renderer loops through definition fields and attaches generic JS/CSS. Conditional display, field names and options come from the definition.
- **Admin** (`includes/admin/`): capability-protected provider/quota configuration with masked secrets and secret-preserving sanitization.

## Generation sequence

1. Resolve the visualizer definition and check provider readiness.
2. Resolve browser identity; check current quota and per-IP hourly allowance before parsing image bytes.
3. Validate upload bytes, MIME, size, dimensions, and submitted options against definition fields.
4. Reserve one generation before making the provider call.
5. Build the prompt from configured base rules, map-driven transformation templates, custom field text, and preservation rules.
6. Send normalized input to the selected provider.
7. On immediate success return a validated HTTPS result URL. On async pending, persist a random job ID with provider job data, visitor ownership hash, quota phase/snapshot, definition slug, and start time; failed quota/job-state writes fail closed and refund where possible.
8. On polling, verify owner and definition slug before contacting the provider. Pending results remain available; completion deletes the job; provider error/timeout refunds the reservation.

## Definitions and prompt system

The renderer and validator iterate fields generically. Registration rejects unsupported field types, duplicate/unsafe IDs, malformed options, invalid numeric ranges, invalid regexes, broken/forward conditions, upload rule errors and out-of-range quota defaults. Select-like fields validate their keys/options; text fields may have configured limits; number/slider fields are clamped; conditional fields reference an earlier field and a value. Image uploads are a core input; a separate image-valued option field is not implemented yet. Prompt maps and templates are data. Add a new visualizer by registering a validated `Definition`; do not add `if ($slug === ...)` checks in the engine, renderer, quota or lead classes.

## Storage

Quota and pending jobs use WordPress transients. Visitor state is keyed from a SHA-256 hash of a random HttpOnly cookie ID. IP rate records store hashed IP keys. Leads are deduplicated by normalized email in a capped WordPress option, validated for phone format, and tagged by visualizer/generation; raw IP addresses are not persisted with lead records. Generated image bytes are checked with image parsers and stored under an engine-owned uploads subdirectory.

## Deliberate Phase 1 limits

No visual definition builder, bathroom definition, image-valued option fields, licensing, billing, advanced analytics, or multi-tenant abstraction. Kitchen is intentionally definition-only: its fields, option maps, conditional custom-instruction field, prompt templates and copy live in `includes/definitions/kitchen.php`; no Kitchen-specific branch is added to Core, Provider, Quota, Lead, REST or renderer code. Provider HTTP uses WordPress safe-URL validation, disables redirects, and caps response bodies; custom polling also pins host and effective port, while Hugging Face jobs are restricted to the fixed router path. Transient read/modify/write operations are not a distributed atomic lock and terminal polling is not a distributed idempotent transaction; high-volume sites should move quotas/jobs to a transactional store in a later phase. This architecture-validation phase uses local fakes only; no live WordPress activation/REST or real AI-provider call is performed.
