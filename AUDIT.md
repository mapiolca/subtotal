# Subtotal compliance audit

## Subtotal margin correction — 2026-11-04 (4.0.2)

- Before correction, regression fixtures on local base `27eb9db8165ae902bf8d4051593b4482c74889ae` reproduced a parent subtotal reporting 199 instead of 140 when it closed an open child title, a catalogue lookup replacing an explicit zero line cost, and a subtotal without a title reporting zero instead of 40. These are synthetic documents; the exact cause of the two 247.50 amounts in the reported screenshot is not confirmed without the original quotation and deployed module revision.
- Margins now select the same title level and preceding line range as `getTotalLineFromObject()`. Ordinary documents call native `FormMargin::getMarginInfosArray()` on cloned document lines, preserving native cost, discount and sign policies without modifying the card's objects. Existing per-line situation progress remains explicit because older native helpers only prorate situation costs in mode 1. No SQL migration or configuration change is needed; historical ID `104777` and family remain unchanged.
- PHP 8.4.22 regression checks cover independent/nested/open/empty blocks, titleless subtotals, products/services, free text, discounted revenue, zero costs, negative sales, the native missing-cost policy and unchanged source objects. Both margin tests execute the actual native FormMargin source; document fixtures, configuration and `price2num()` are simulated, so this does not validate database lookups, native rounding settings or a full instance. The existing situation test now uses real Subtotal block selection.
- All eleven repository test scripts passed against local Dolibarr `25.0.0-alpha` sources, including descriptor/PDF discovery, native HookManager checks and 36 automatic-generation scenarios. PHP lint passed for all eight changed/new PHP files; `git diff --check` passed. PHPStan level 5 (`phpstan.neon.dist`) was not run because no executable is installed in the available tooling. No full-instance browser validation, deployment, package installation, remote CI or full Dolibarr/PHP compatibility matrix was executed. The original quotation must still be checked after deployment.
- The card summary now displays `Subtotal: title | Cost price: value | Margin: value | Amount (excl. tax): value`, using native `SubTotal`, `CostPrice`, `Margin` and `AmountHT` translations and `price()` formatting. Costs and margins use one shared calculation and follow the existing display setting. Title selection respects the subtotal level; text is stripped of markup and escaped. No extra table column is added. `test/subtotal_summary_test.php` renders the actual hook and checks the text order, cost values, escaping and unchanged cell count with margins enabled/disabled. An internal-browser fixture was visually checked at 1280 px and 400 px, with long titles and horizontal scrolling on narrow screens. Page context, price formatting and surrounding CSS were simulated; this is not a deployed Dolibarr card or theme validation.

Native margin helper sources executed locally (not full instance tests):

| Dolibarr source | Commit | Both margin regressions |
|---|---|---|
| 16.0.0 (historical baseline) | `ad673b3207506de03ee8e745399e3a19505900d2` | Passed |
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | Passed |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` | Passed |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` | Passed |
| 23.0.0 | `57a1f05d490a7a80944a8232e9c613e6556d2704` | Passed |
| 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` | Passed |
| 25.0.0-alpha (local development checkout) | `0ac96524bf68b063a44d6af8137e05bf11c227c8` | Passed |

The executed symbol is `FormMargin::getMarginInfosArray()` in `htdocs/core/class/html.formmargin.class.php`, for example [the pinned v20 implementation](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/core/class/html.formmargin.class.php). This proves neither its first appearance nor compatibility with a future final v25 release. The About page reads the descriptor version directly and needs no duplicate version edit.

## Decisions applied in 4.0.0

- The family is `ATM Consulting x Les Métiers du Bâtiment`.
- Historical module ID `104777` and line `special_code` values remain unchanged.
- Dolibarr 16 / PHP 7 compatibility is an explicit historical exception; Dolibarr 20 / PHP 8 is recommended.
- AJAX, REST and hook mutations share the same native-rights and Multicompany access checks.
- Administration uses the native Settings, Compatibility and About tabs.
- Core copies and web-executable maintenance scripts are removed.

## Validation still requiring a full Dolibarr instance

- Activation, disable and reactivation on Dolibarr 16 and the current stable version.
- Browser tests for protected AJAX mutations and native administration controls.
- Two-entity Multicompany document generation and dictionary isolation.
- PDF visual verification with long and HTML free text, intermediate footers and active footer hooks.
- Dolibarr-aware PHPStan execution with the full core available.

## Targeted corrections checked on 2026-10-26

- The end-of-section insertion defect was reproduced before the patch: a section without a closing subtotal left the new line at the document end. Eleven regression scenarios now cover open/closed/nested sections, ordinary quantities matching subtotal markers, invalid title ranks and rank-write failures. Rank writes are simulated; this does not validate a real database transaction or concurrent edits.
- The title selector uses the native `ajax_combobox()` with a bounded width and a separate dialog ID. Native-helper tests cover long labels, escaped markup and the non-Ajax fallback. The collapse toolbar uses the effective header column count and is not duplicated on reinitialization.
- A local browser fixture using the module's selector, its toolbar JavaScript and Dolibarr 24.0.2 jQuery/Select2 assets reproduced horizontal overflow with a long label: page width 2437 px for a 1280 px viewport before, then 1280 px after. The corrected toolbar spans 14 columns and remains unique after two initializations. The table/form and CSS are simulated, not a deployed Dolibarr card.
- `fix/php82-dynamic-properties` (`76cca215d7ed68452fa9d26264a21935c47d4440`) was only partially present in the 4.0.0 preparation. The native `menu` declaration was already correct; the remaining obsolete `special` assignment is removed. Descriptor discovery now passes under PHP 8.4.22 with deprecations treated as errors, using the native Dolibarr 24.0.2 parent class and simulated configuration, without activation or database writes.
- Native sources used for these targeted helper/discovery checks: Dolibarr 24.0.2, commit `7e927764767343334aaa067ca5ec054cfea0a26b`. This is not a full validation of Dolibarr 20–25 or of the historical Dolibarr 16 / PHP 7 baseline.
- Production was not modified. Browser access to the production card was unavailable, so neither the served Subtotal revision nor the final production behavior is confirmed. PHPStan is not installed in the available module/core tooling; the configured level 5 analysis was not executed.

## Integration and browser checks on 2026-10-26

- Module icon: native helper calls with Dolibarr `24.0` (`fb6a38d`) and the local development revision (`31174f8`) confirmed that `modsubtotal@subtotal` resolved differently: `object_modsubtotal.png` in 24, but the missing `modsubtotal.png` in the development core. Commit `19d308b` temporarily used the native `fa-list-alt` pictogram; About reused the descriptor value. Configuration and translations were simulated for these helper checks, with no activation or database write.

- Real browser checks on a non-production Dolibarr 25.0.0-alpha / PHP 8.3.36 instance used tree `6c2bc2e`: closed-section insertion, open-section insertion before the next peer title, immediate insertion under a title, and an ordinary product with quantity 99 all persisted in the expected order after reload.
- Settings, Compatibility and About displayed successfully. The descriptor reported 4.0.0. Activation, disable and reactivation completed without a visible error; temporary option changes and the initial disabled state were restored.
- With a long title and 14 displayed columns, the selector remained 200 px wide and the folding toolbar stayed unique with colspan 14. At a 1920 px viewport, both the available width and document width were 1905 px. At 1280 px, the document remained 1709 px wide for 1265 px available, including with the title selector disabled: the remaining narrow-window layout constraint is not fixed by the title-selector correction.
- These real checks used one administrative account and one entity. They do not validate rights refusals, interentity access, API behavior, detailed PDF layout or the full compatibility matrix. Generated files and the dedicated draft were retained.
- The authorized merge of master preserves the 4.0.0 metadata and situation-card corrections, integrates the shared situation-total calculation and its regression test, and removes duplicate PDF helper declarations created by the automatic merge. Results from before this merge must not be presented as tests of the final merged code.

## Original module icon compatibility on 2026-10-27

- The descriptor restores `modsubtotal@subtotal`. The original `img/object_modsubtotal.png` is retained and copied byte-for-byte to `img/modsubtotal.png`, the filename requested by the development core. This preserves the original artwork without a version condition, a core override or a new icon library. About continues to use the descriptor value.
- PHP 8.4.22 checks passed: descriptor lint and discovery, static compliance, native `img_object()` resolving the existing image in `24.0` (`fb6a38d`) and development (`31174f8`), and identical SHA-256 hashes for both PNG files. Helper configuration and translations were simulated; these checks do not validate a full 24.0 instance or the complete compatibility matrix. PHPStan remains unavailable locally.

## Hidden PDF lines review correction on 2026-10-27

- PR #6 review thread `PRRT_kwDOCcpUu86wR_dO` identified a real regression: the card restored the saved choice into a global, while PDF consumers still read only the request. The new regression failed on `a1a248a` with a product retained during automatic regeneration despite the saved enabled choice.
- The card hook now records the manual or restored choice by document element and ID. All five active PDF consumers resolve an explicit request value first, then that document's restored value. An unchecked manual generation records zero; no request injection or additional session reads are introduced.
- `php test/pdf_total_mutation_test.php [path/to/htdocs]` covers six automatic actions on all six customer/supplier proposal, order and invoice card contexts (36 scenarios), subtotal rendering without financial mutations, explicit and saved zero, and isolation between document IDs and types. The optional native HookManager check adds the PDF context after the card action and runs successive documents with simulated module configuration and PDF rendering.
- PHP 8.4.22 checks passed with native HookManager sources from `a88bcd27b1cb860a1312df7a28dd9b9438c2979c` and `31174f80625188edb08634ae34e4fdbdb3c75f5d`. Static compliance, lint, descriptor discovery, the native title selector and existing insertion/column/situation tests also passed. Enabling `E_ALL` still reports the pre-existing optional-before-required parameter deprecations in legacy hook signatures; this patch does not change those signatures.
- These are local regression checks, not a full PDF generation or database-backed validation/reopening on every supported Dolibarr version. The historical compatibility declarations are unchanged; PHPStan level 5 remains unavailable locally.
