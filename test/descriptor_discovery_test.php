<?php
/**
 * Instantiate the descriptor against a real core without activating the module.
 * Run with: php test/descriptor_discovery_test.php /path/to/dolibarr/htdocs
 *
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}
$coreRoot = isset($argv[1]) ? realpath($argv[1]) : false;
if ($coreRoot === false || !is_file($coreRoot.'/core/modules/DolibarrModules.class.php')) {
	fwrite(STDERR, "Pass the path to a Dolibarr htdocs directory.\n");
	exit(1);
}
define('DOL_DOCUMENT_ROOT', $coreRoot);
define('MAIN_DB_PREFIX', 'test_');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

// Only translations and activation state are simulated; the parent class is native.
function isModEnabled($module)
{
	return false;
}
class DescriptorTestTranslations
{
	public function trans($key)
	{
		return $key;
	}
}
$langs = new DescriptorTestTranslations();
$conf = (object) array('entity' => 1);
require_once __DIR__.'/../core/modules/modSubtotal.class.php';
$descriptor = new modSubtotal(null);
if ($descriptor->version !== '4.0.0' || $descriptor->menu !== array()) {
	throw new RuntimeException('Unexpected descriptor version or native menu declaration.');
}
restore_error_handler();
print 'OK: native descriptor discovery without PHP deprecations on PHP '.PHP_VERSION.".\n";
