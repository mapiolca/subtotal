# Subtotal maintenance decisions

This file supplements the shared Dolibarr module development instructions for this module.

## Supported environments

- Historical compatibility is retained from Dolibarr 16 and PHP 7.0.
- Dolibarr 20 or later with PHP 8.0 or later is the recommended and primary validation environment.
- Compatibility code must never require a core file to be copied or replaced.
- Features unavailable on an older Dolibarr version must be filtered and reported on the Compatibility page.

## Approved identity exceptions

- The approved module family is `ATM Consulting x Les Métiers du Bâtiment`.
- Module ID `104777` is retained because it is historically deployed and is also stored as the `special_code` of Subtotal lines.
- Existing `SUBTOTAL_*` constants, document model names and technical line markers remain stable.
- These decisions are explicit exceptions to the usual `Les Métiers du Bâtiment` family, `450000-450999` ID range and Dolibarr 20 / PHP 8 minimum rules.

## Module-specific invariants

- Subtotal extends native commercial documents and reuses their native read and write permissions.
- A line may be read or mutated only after its parent document, entity and user access have been validated.
- The free-text dictionary is private to the current entity and is not shared through Multicompany.
- Documents are always read and written in the directory of the entity owning the parent object.
- Module settings, selected document models and historical line markers survive disable/enable cycles.
- Web endpoints never disable Dolibarr CSRF protection.

