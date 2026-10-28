<?php
/**
 * Discover legacy PDF models with native core parents and strict error reporting.
 * Run with: php test/pdf_discovery_test.php /path/to/dolibarr/htdocs
 *
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}
$coreRoot = isset($argv[1]) ? realpath($argv[1]) : false;
if ($coreRoot === false || !is_file($coreRoot.'/core/lib/functions.lib.php')) {
	fwrite(STDERR, "Pass the path to a Dolibarr htdocs directory.\n");
	exit(1);
}
define('DOL_DOCUMENT_ROOT', $coreRoot);
define('MAIN_DB_PREFIX', 'test_');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

// Simulate translations, configuration and the paper-format lookup; no database writes.
class PdfDiscoveryTranslations
{
	public $defaultlang = 'fr_FR';
	public function load($catalogue)
	{
	}
}
class PdfDiscoveryDatabase
{
	public function escape($value)
	{
		return $value;
	}
	public function query($sql)
	{
		if (strpos($sql, 'SELECT ') !== 0 || strpos($sql, 'test_c_paper_format') === false) {
			throw new RuntimeException('Unexpected database query: '.$sql);
		}
		return false; // Native pdf_getFormat() uses its default A4 format.
	}
}
$langs = new PdfDiscoveryTranslations();
$db = new PdfDiscoveryDatabase();
$conf = (object) array(
	'global' => new stdClass(),
	'file' => (object) array('dol_document_root' => array('main' => $coreRoot, 'alt0' => dirname(__DIR__, 2))),
);
$mysoc = (object) array('tva_assuj' => 1, 'country_code' => 'FR');
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

$models = array('facture' => 'crabe', 'commande' => 'einstein', 'propale' => 'azur');
foreach ($models as $type => $name) {
	$class = 'pdf_'.$name.'_subtotal';
	require_once __DIR__.'/../core/modules/'.$type.'/doc/'.$class.'.modules.php';
	foreach (array(0, 1) as $vatLiable) {
		$mysoc->tva_assuj = $vatLiable;
		$model = new $class($db);
		if ($model->option_codeproduitservice !== 1 || $model->franchise !== !$vatLiable) {
			throw new RuntimeException('Legacy PDF options changed for '.$class);
		}
		if (urlencode($model->scandir) !== '') {
			throw new RuntimeException('A PHP PDF model must not scan an ODT template directory.');
		}
	}
}
restore_error_handler();
print 'OK: 3 native PDF models, both VAT modes and admin scan_dir encoding on PHP '.PHP_VERSION.".\n";
