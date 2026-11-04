# Subtotal 4.1.0

Manage predefined texts directly from documents with a dedicated permission.

Subtotal adds titles, subtitles, free text and subtotal rows to supported Dolibarr proposals, orders, invoices and supplier documents.

Situation invoice rows follow the effective native table column count. A subtotal margin sums each line contribution prorated by that line’s progress in the current situation.

On proposals, orders and regular invoices, subtotal margins use Dolibarr's native calculation on the relevant block lines. Document buying prices, including zero, take precedence over current catalogue costs. Nested blocks use the same line range as the subtotal amount excluding tax.

Card summaries share a single cell: **Subtotal: title | Cost price: value | Margin: value (rate %) | Amount (excl. tax): value**. The title matches the subtotal level. Costs and margins follow the existing display setting. The margin rate uses buying cost as its denominator and is omitted when that cost is zero or negative. Title underlining does not apply to the margin.

## Predefined texts from documents

The free-text insertion dialog uses native Dolibarr Select2 selectors when enabled. Its “+” opens a wide dictionary manager with search, pagination, a native HTML editor (or textarea), activation and confirmed deletion. Labels are required and limited to 255 characters; content is optional.

Closing the manager refreshes choices while keeping the selected block and custom description. New entries are never selected automatically. Explicitly selecting a template copies its content into the editor; OK inserts only the final edited content. Dictionary changes never alter texts already copied into documents.

HTML sanitization retains text formatting, lists and tables; CSS styles, links and embedded resources are removed when copying or saving.

After updating the files, open native user/group permissions to register missing rights for the active module. Grant **Manage predefined texts** (`10477701`) to the appropriate users or groups; it is not granted by default. Management also requires an internal user and read/write access to the draft document. Existing templates remain usable with document permissions alone. Native module reactivation is also supported and preserves module settings.

The existing administrative dictionary and table are retained. Dictionary data belongs exclusively to the current entity, including when the document is shared from another entity. Conflicting changes are rejected while keeping the submitted input.

## Compatibility

- Dolibarr 16 or later;
- PHP 7.0 or later;
- Dolibarr 20+ and PHP 8.0+ recommended.

Detailed runtime information is available from the module’s **Compatibility** settings tab.

## Maintenance and security

Legacy web migrations were replaced with dry-run-first CLI commands in `scripts/maintenance/`. Ajax and REST operations validate native parent-document permissions, entity access and external-user scope. Generated documents use the owning entity’s native output directory.

## License

GNU GPL v3 or later.
