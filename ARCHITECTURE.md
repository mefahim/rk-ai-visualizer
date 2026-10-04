# Architecture

## Layers

- **Definition** (`includes/definitions/`): business-facing data: identity, upload constraints, field schema/options, prompt configuration, copy/CTA and quota defaults. Flooring is the Phase 1 implementation. The definition contains no execution logic.
- **Core** (`includes/core/`): reusable engine, validation, prompt composition, quota reservations/refunds, lead persistence, image storage/cleanup, and normalized errors. Core methods receive a definition and do not branch on a particular visualizer slug or field identifier.
- **Provider** (`includes/providers/`): `ProviderInterface` maps normalized inputs to normalized results. Gemini is synchronous; Hugging Face and custom backends can return jobs; mock is synchronous. Provider-specific payloads and response formats stay in adapter classes.
- **REST** (`includes/rest/`): REST paths resolve a slug and delegate to the engine. Legacy non-definition paths default to Flooring.
- **Frontend** (`includes/frontend/`, `assets/`): the shortcode renderer loops through definition fields and attaches generic JS/CSS. Conditional display, field names and options come from the definition.
- **Admin** (`includes/admin/`): capability-protected provider/quota configuration with masked secrets and secret-preserving sanitization.

## Generation sequence

1. Resolve the visualizer definition and check provider readiness.
2. Validate upload bytes, MIME, size, dimensions, and submitted options against definition fields.
3. Resolve browser identity; check current quota and per-IP hourly allowance.
4. Reserve one generation before making the provider call.
5. Build the prompt from configured base rules, map-driven transformation templates, custom field text, and preservation rules.
6. Send normalized input to the selected provider.
7. On immediate success return a validated HTTPS result URL. On async pending, store a random job ID with provider job data, visitor ownership hash, quota phase/snapshot, definition slug, and start time.
8. On polling, verify owner before contacting the provider. Pending results remain available; completion deletes the job; provider error/timeout refunds the reservation.

## Definitions and prompt system

The renderer and validator iterate fields generically. Select-like fields validate their keys/options; text fields may have configured limits; number fields are clamped; conditional fields reference another field and a value. Prompt maps and templates are data. Add a new visualizer by registering a validated `Definition`; do not add `if ($slug === ...)` checks in the engine, renderer, quota or lead classes.

## Storage

Quota and pending jobs use WordPress transients. Visitor state is keyed from a SHA-256 hash of a random HttpOnly cookie ID. IP rate records store hashed IP keys. Leads are deduplicated by normalized email in a capped WordPress option and tagged by visualizer/generation. Generated image bytes are checked with image parsers and stored under an engine-owned uploads subdirectory.

## Deliberate Phase 1 limits

No visual definition builder, kitchen/bathroom definitions, cloud queue, licensing, billing, advanced analytics, or multi-tenant abstraction. Transient read/modify/write operations are not a distributed atomic lock; high-volume sites should move quotas/jobs to a transactional store in a later phase. Custom provider request/response contracts are intentionally modest and should be expanded only against concrete backend requirements.
