# Subtotal 4.0.2

Patch version in preparation since **2026-11-04**, not published.

Subtotal adds titles, subtitles, free text and subtotal rows to supported Dolibarr proposals, orders, invoices and supplier documents.

Situation invoice rows follow the effective native table column count. A subtotal margin sums each line contribution prorated by that line’s progress in the current situation.

On proposals, orders and regular invoices, subtotal margins use Dolibarr's native calculation on the relevant block lines. Document buying prices, including zero, take precedence over current catalogue costs. Nested blocks use the same line range as the subtotal amount excluding tax.

Card rows explicitly label **Subtotal: title | Cost price: value | Margin: value | Amount (excl. tax): value** in this order. The title matches the subtotal level. Costs and margins follow the existing display setting; the amount stays in its native column.

## Compatibility

- Dolibarr 16 or later;
- PHP 7.0 or later;
- Dolibarr 20+ and PHP 8.0+ recommended.

Detailed runtime information is available from the module’s **Compatibility** settings tab.

## Maintenance and security

Legacy web migrations were replaced with dry-run-first CLI commands in `scripts/maintenance/`. Ajax and REST operations validate native parent-document permissions, entity access and external-user scope. Generated documents use the owning entity’s native output directory.

## License

GNU GPL v3 or later.
