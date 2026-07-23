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
