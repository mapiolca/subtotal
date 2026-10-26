# Subtotal compliance audit

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

- Module icon: native helper calls with Dolibarr `24.0` (`fb6a38d`) and the local development revision (`31174f8`) confirm that the former `modsubtotal@subtotal` value resolves differently: `object_modsubtotal.png` in 24, but the missing `modsubtotal.png` in the development core. The descriptor now uses the native `fa-list-alt` pictogram, rendered by both helpers without an image URL; About reuses the descriptor value. Configuration and translations are simulated for these helper checks, with no activation or database write.

- Real browser checks on a non-production Dolibarr 25.0.0-alpha / PHP 8.3.36 instance used tree `6c2bc2e`: closed-section insertion, open-section insertion before the next peer title, immediate insertion under a title, and an ordinary product with quantity 99 all persisted in the expected order after reload.
- Settings, Compatibility and About displayed successfully. The descriptor reported 4.0.0. Activation, disable and reactivation completed without a visible error; temporary option changes and the initial disabled state were restored.
- With a long title and 14 displayed columns, the selector remained 200 px wide and the folding toolbar stayed unique with colspan 14. At a 1920 px viewport, both the available width and document width were 1905 px. At 1280 px, the document remained 1709 px wide for 1265 px available, including with the title selector disabled: the remaining narrow-window layout constraint is not fixed by the title-selector correction.
- These real checks used one administrative account and one entity. They do not validate rights refusals, interentity access, API behavior, detailed PDF layout or the full compatibility matrix. Generated files and the dedicated draft were retained.
- The authorized merge of master preserves the 4.0.0 metadata and situation-card corrections, integrates the shared situation-total calculation and its regression test, and removes duplicate PDF helper declarations created by the automatic merge. Results from before this merge must not be presented as tests of the final merged code.
