<?php
/**
 * Lightweight repository checks that do not require a Dolibarr runtime.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

$root = dirname(__DIR__, 2);
$errors = array();

$required = array('AGENT.md', 'AUDIT.md', 'ChangeLog.md', 'modulebuilder.txt', 'admin/setup.php', 'admin/compatibility.php', 'admin/about.php');
foreach ($required as $relativePath) {
	if (!is_file($root.'/'.$relativePath)) {
		$errors[] = 'Missing '.$relativePath;
	}
}

$forbidden = array('backport', 'htdocs_38', 'A_LIRE.TXT');
foreach ($forbidden as $relativePath) {
	if (file_exists($root.'/'.$relativePath)) {
		$errors[] = 'Forbidden legacy path '.$relativePath;
	}
}

$descriptor = is_file($root.'/core/modules/modSubtotal.class.php') ? file_get_contents($root.'/core/modules/modSubtotal.class.php') : '';
foreach (array("'4.0.0'", "'ATM Consulting x Les Métiers du Bâtiment'", "'https://lesmetiersdubatiment.fr'", "'setup.php@subtotal'") as $needle) {
	if (strpos($descriptor, $needle) === false) {
		$errors[] = 'Descriptor does not contain '.$needle;
	}
}
foreach (array("'compatibility.php@subtotal'", "'about.php@subtotal'") as $needle) {
	if (strpos($descriptor, $needle) !== false) {
		$errors[] = 'Descriptor contains an extra settings entry '.$needle;
	}
}

$ajaxController = file_get_contents($root.'/script/interface.php');
foreach (array('NOCSRFCHECK', 'NOTOKEN') as $needle) {
	if (strpos($ajaxController, $needle) !== false) {
		$errors[] = 'AJAX controller contains forbidden bypass '.$needle;
	}
}

$apiController = file_get_contents($root.'/class/api_subtotal.class.php');
if (strpos($apiController, '/custom/subtotal/') !== false) {
	$errors[] = 'REST API contains a hardcoded custom module path';
}

$pdfLibrary = file_get_contents($root.'/lib/subtotal_pdf.lib.php');
foreach (array("function_exists('getMultidirOutput')", 'multidir_output[$entity]') as $needle) {
	if (strpos($pdfLibrary, $needle) === false) {
		$errors[] = 'Multicompany PDF helper does not contain '.$needle;
	}
}

$maintenanceBootstrap = file_get_contents($root.'/scripts/maintenance/bootstrap.php');
if (strpos($maintenanceBootstrap, "!isset(\$options['entity'])") === false) {
	$errors[] = 'Maintenance commands do not require an explicit entity';
}

$dictionaryKeys = file_get_contents($root.'/sql/llx_c_subtotal_free_text.key.sql');
if (stripos($dictionaryKeys, 'UNIQUE') !== false) {
	$errors[] = 'Dictionary update introduces a potentially destructive unique constraint';
}

$hookClass = file_get_contents($root.'/class/actions_subtotal.class.php');
foreach (array('getSituationLineProgressRatio', 'getSubtotalMargin', "=== 2 ? 3 : 2", 'subtotal-colspan-cell', 'alignSubtotalRowsToHeader') as $needle) {
	if (strpos($hookClass, $needle) === false) {
		$errors[] = 'Situation-invoice implementation does not contain '.$needle;
	}
}

$languageKeys = array();
foreach (array('fr_FR', 'en_US', 'es_ES') as $locale) {
	$file = $root.'/langs/'.$locale.'/subtotal.lang';
	$keys = array();
	foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
		if (preg_match('/^\s*([A-Za-z0-9_]+)\s*=/', $line, $matches)) {
			$keys[$matches[1]] = true;
		}
	}
	$languageKeys[$locale] = $keys;
}
$referenceKeys = array_keys($languageKeys['fr_FR']);
sort($referenceKeys, SORT_STRING);
foreach ($languageKeys as $locale => $keys) {
	$currentKeys = array_keys($keys);
	sort($currentKeys, SORT_STRING);
	if ($currentKeys !== $referenceKeys) {
		$errors[] = 'Translation-key mismatch for '.$locale;
	}
}

if ($errors) {
	fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
	exit(1);
}

print "Subtotal static compliance checks passed.\n";
