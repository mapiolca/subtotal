<?php
/**
 * Test the title selector with native Dolibarr helpers and simulated configuration.
 * Run with: php test/title_selector_test.php /path/to/dolibarr/htdocs [--html]
 *
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}
$coreRoot = isset($argv[1]) ? realpath($argv[1]) : false;
if ($coreRoot === false || !is_file($coreRoot.'/core/lib/ajax.lib.php')) {
	fwrite(STDERR, "Pass the path to a Dolibarr htdocs directory.\n");
	exit(1);
}
define('DOL_DOCUMENT_ROOT', $coreRoot);
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
if (is_file(DOL_DOCUMENT_ROOT.'/core/lib/html.lib.php')) {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/html.lib.php';
}
$conf = (object) array(
	'global' => (object) array('MAIN_USE_JQUERY_MULTISELECT' => 1),
	'use_javascript_ajax' => 1,
	'file' => (object) array('dol_document_root' => array('main' => $coreRoot, 'alt0' => dirname(__DIR__, 2))),
);
class SelectorTestTranslations
{
	public function trans($key)
	{
		return $key;
	}
}
$langs = new SelectorTestTranslations();
require_once __DIR__.'/../class/subtotal.class.php';
require_once __DIR__.'/../lib/subtotal.lib.php';

$plainLabel = rtrim(str_repeat('Titre long de section ', 12)).' & "texte"';
$label = str_repeat('Titre long de section ', 12).'<img src=x onerror="alert(1)"> & "texte"';
$object = (object) array('lines' => array((object) array(
	'id' => 1, 'rang' => 1, 'qty' => 1, 'product_type' => 9,
	'special_code' => TSubtotal::$module_number, 'label' => $label, 'desc' => '',
)));
$html = getHtmlSelectTitle($object);
$dialogHtml = getHtmlSelectTitle($object, true);
$conf->use_javascript_ajax = 0;
$fallbackHtml = getHtmlSelectTitle($object);

$document = new DOMDocument();
libxml_use_internal_errors(true);
$document->loadHTML('<meta charset="utf-8">'.$html.$dialogHtml);
libxml_clear_errors();
$xpath = new DOMXPath($document);
if ($xpath->query('//select[@name="under_title"]')->length !== 2
	|| $xpath->query('//select[@id="subtotal_under_title"]')->length !== 1
	|| $xpath->query('//select[@id="subtotal_under_title_dialog"]')->length !== 1
	|| $xpath->query('//option[@value="1"]')->item(0)->textContent !== $plainLabel
	|| $xpath->query('//option//script')->length !== 0
	|| strpos($html, "width: '200px'") === false
	|| strpos($fallbackHtml, '<script>') !== false
	|| strpos($fallbackHtml, 'width:200px') === false) {
	throw new RuntimeException('Selector must preserve the title as text and bound both native and Select2 widths.');
}

if (in_array('--html', $argv, true)) {
	echo $html;
} else {
	print "OK: native Select2, bounded fallback, separate dialog ID and escaped long title.\n";
}
