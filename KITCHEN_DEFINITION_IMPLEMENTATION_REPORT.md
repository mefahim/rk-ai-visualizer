# Kitchen Definition — Architecture Validation Report

## Summary

Added Kitchen Visualizer as a second registered definition on `feat/kitchen-definition`, based on the fast-forwarded, merged Phase 1.5 `main`. The implementation uses the existing definition schema and generic execution paths. No live WordPress or real AI-provider testing was performed; the mock provider was used only in the local code-level suite.

## Files created/changed

| File | Change |
| --- | --- |
| `includes/definitions/kitchen.php` | New `Kitchen::definition()` data class containing Kitchen fields, option maps, prompt rules, copy, upload limits, and quota defaults. |
| `includes/bootstrap.php` | Requires and registers the Kitchen definition alongside Flooring through the existing registry mechanism. |
| `tests/bootstrap.php` | Adds valid Kitchen test-option defaults. |
| `tests/run.php` | Adds eight architecture-validation tests for Kitchen and explicit Flooring regression coverage. |
| `README.md` | Documents the Kitchen shortcode and the shared definition-driven endpoints. |
| `ARCHITECTURE.md` | Records Kitchen as a definition-only validation and removes the obsolete “Kitchen definitions” deferred claim. |
| `KITCHEN_DEFINITION_IMPLEMENTATION_REPORT.md` | This report. |

## New fields

The Kitchen definition adds the following ordered controls, all using field types already supported by the generic `Definition` and renderer:

- `roomType`: residential, apartment, open-plan, or compact/secondary kitchen.
- `kitchenLayout`: preserve existing, galley, L-shaped, U-shaped, island, or peninsula.
- `cabinetStyle`: Shaker, flat panel, slab, inset, traditional raised panel, or custom.
- `cabinetColor`: warm/soft white, natural oak, walnut, sage, navy, charcoal, or light gray.
- `countertopMaterial` and `countertopColor`.
- `backsplashStyle`, `backsplashMaterial`, and `backsplashColor`.
- `flooring`, including keep-existing and common hard-surface alternatives.
- `hardwareFinish`, `applianceStyle`, and `lightingPreference`.
- `customDesignInstructions`: a 600-character textarea, visible and required only when `cabinetStyle=custom`.

Every choice is represented by the existing select field contract and explicit option values/labels. Upload constraints, quota defaults, CTA and copy are definition data as well.

## Prompt architecture

The definition uses the existing `Prompt::build()` contract only: base rules, field-keyed transformation maps, templated transformation rules, one configured custom field, then preservation rules. Option maps translate each selected value into visible material/style language; the templates direct those values to the corresponding cabinets, counters, backsplash, floor, hardware, appliances and lighting.

The prompt states what can change—selected finishes and visible kitchen elements, with cabinet/island arrangement changes bounded to the existing footprint—and what must remain stable: room envelope and structural openings, camera/lens/crop/perspective, room scale, fixed plumbing and major-appliance locations, lighting direction and natural shadows. Custom text is appended through the existing sanitized custom-instruction path; the preservation rules follow it to reinforce structural and camera constraints.

## Tests and final result

Eight tests were added, covering Kitchen registration/schema, valid and invalid selections, the conditional custom-instruction field, prompt composition, generic frontend rendering, mock-provider generation and Kitchen quota consumption, generic REST quota/lead resolution by slug, and a dedicated Flooring definition/prompt/default-renderer regression.

**Full suite result: 35 tests, 0 failures.** The suite includes the original 27 tests plus eight new tests. All PHP files pass `php -l`; the test suite was run with `E_ALL` and display errors enabled. No external requests were made.

## Generic architecture confirmation

Kitchen-specific behavior remains in `includes/definitions/kitchen.php`. Registration is the only application wiring change: the existing bootstrap loads and registers the new definition. No Kitchen-specific slug/field checks were added to the generic Core Engine, validation, prompt composer, quota, lead, provider adapters, REST route handlers, or frontend renderer. The core/provider/quota/lead/REST/renderer implementation files were not modified for this phase. Existing Flooring behavior is covered by both the pre-existing tests and the new regression test.

## Architectural limitations discovered

- Definitions are currently PHP-authored and registered at bootstrap; there is no admin definition builder or dynamic runtime definition loading.
- Conditions support a single equality check against an earlier field, not compound predicates, exclusions, or arbitrary dependency graphs. This is sufficient for the custom-style field.
- Prompt composition currently supports one configured `custom_field` per definition. Kitchen needs only one; several separately composed custom instruction fields would require a broader generic contract.
- The schema does not express cross-field consistency constraints, such as automatically coupling a “minimal/no backsplash” choice to a particular material/color choice. The current definition keeps these as explicit independent design selections.
- This validates definition registration and fake-based code paths, not image quality, live WordPress route/renderer behavior, or real Gemini/Hugging Face request/response compatibility; those remain deferred.

## Next step

Review the architecture-validation PR and, if accepted, test the definition on a disposable WordPress environment only in a separately approved integration phase. Do not expand the generic engine solely to address production polish until concrete integration findings require it.
