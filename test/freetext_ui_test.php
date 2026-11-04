<?php
/**
 * Generate an isolated browser fixture: actual native helpers/widgets and module JS, mocked Ajax data.
 * Run: php test/freetext_ui_test.php /path/to/dolibarr/htdocs > fixture.html
 * Serve the fixture with the repository as /subtotal and core static assets as /_core.
 * No instance, authentication or SQL behavior is validated here.
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
if (PHP_SAPI !== 'cli') exit(1);
$argv[2] = '--fixtures';
require __DIR__.'/freetext_model_test.php';
define('DOL_URL_ROOT', '/_core');
foreach (array(3, 8) as $rows) if (!defined('ROWS_'.$rows)) define('ROWS_'.$rows, $rows);
$_SESSION = array('token' => 'fixture', 'newtoken' => 'fixture');
$_SERVER['PHP_SELF'] = '/subtotal/script/freetext.php';
$conf->cache = array('nonce' => 'fixture');
$conf->browser = (object) array('name' => 'chrome', 'os' => 'windows');
$conf->theme = 'eldy';
$conf->currency = 'EUR';
$conf->liste_limit = 20;
$conf->use_javascript_ajax = 1;
$conf->dol_optimize_smallscreen = 0;
$conf->main_checkbox_left_column = 0;
$conf->global->MAIN_USE_JQUERY_MULTISELECT = 1;
$conf->global->FCKEDITOR_ENABLE_DETAILS = 1;
$conf->file = (object) array('dol_document_root' => array('main' => $coreRoot, 'alt0' => dirname(__DIR__, 2)), 'dol_url_root' => array('main' => '/_core', 'alt0' => ''));
$langs->defaultlang = 'fr_FR';
foreach (array($coreRoot.'/langs/fr_FR/main.lang', $coreRoot.'/langs/fr_FR/admin.lang', __DIR__.'/../langs/fr_FR/subtotal.lang') as $file) {
	foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) if (preg_match('/^([A-Za-z0-9_]+)=(.*)$/', $line, $match)) $langs->map[$match[1]] = $match[2];
}
require_once __DIR__.'/../lib/subtotal_freetext.lib.php';
require_once __DIR__.'/../lib/subtotal.lib.php';
$db = new FreeTextTestDB();
$entry = new SubtotalFreeText($db, 1);
$blankForm = subtotalFreeTextForm($entry);
$conf->modules['fckeditor'] = 1;
$conf->fckeditor = (object) array('enabled' => 1);
$richForm = subtotalFreeTextForm($entry);
$entry->mutate('create', $user, array('label' => 'Conditions de prestation', 'content' => '<p>Conditions <strong>initiales</strong></p>'));
$query = array('page' => 0, 'limit' => 20, 'search' => '', 'sortfield' => 'label', 'sortorder' => 'ASC', 'element' => 'propal', 'elementid' => 1);
$list = subtotalFreeTextList($entry->listEntries(), $query);
$emptyList = subtotalFreeTextList(array('rows' => array(), 'total' => 0), $query);
$many = $entry->listEntries(); $many['total'] = 25;
for ($i = 1; $i < 20; $i++) { $many['rows'][$i] = $many['rows'][0]; $many['rows'][$i]['id'] = $i + 1; }
$pageList = subtotalFreeTextList($many, $query);
$conf->main_checkbox_left_column = 1;
$leftList = subtotalFreeTextList($entry->listEntries(), $query);
$document = new DOMDocument();
libxml_use_internal_errors(true);
$document->loadHTML('<meta charset="utf-8">'.$list.$leftList);
$xpath = new DOMXPath($document);
if ($xpath->query('//select[@id="limit"]/ancestor::form')->length !== 2 || $xpath->query('//input[@type="hidden" and @name="limit"]')->length !== 0
	|| $xpath->query('(//form)[1]//tr[contains(@class,"liste_titre_filter")]/td[last()]//button')->length !== 2
	|| $xpath->query('(//form)[2]//tr[contains(@class,"liste_titre_filter")]/td[last()]//button')->length !== 2
	|| $xpath->query('//tr[@data-id]/td[last()]//a[contains(@class,"subtotal-freetext-edit")]')->length !== 2
	|| $xpath->query('//tr[@data-id]/td[last()]//a[contains(@class,"subtotal-freetext-delete")]')->length !== 2
	|| $xpath->query('//tr[@data-id]/td[1]//a')->length !== 0
	|| $xpath->query('//tr[contains(@class,"liste_titre") and not(contains(@class,"liste_titre_filter"))]/*[last()][normalize-space(.)="Actions"]')->length !== 2) {
	throw new RuntimeException('Native pagination form contract changed');
}
foreach ($xpath->query('//tr[contains(@class,"liste_titre") and not(contains(@class,"liste_titre_filter"))]') as $header) {
	$titles = array();
	foreach ($header->childNodes as $cell) if ($cell instanceof DOMElement) $titles[] = trim($cell->textContent);
	if ($titles !== array($langs->trans('Label'), $langs->trans('Content'), $langs->trans('Status'), $langs->trans('Actions'))) {
		throw new RuntimeException('Dictionary column order changed');
	}
}
$object = (object) array('element' => 'propal', 'id' => 1, 'lines' => array((object) array('id' => 1, 'rang' => 1, 'qty' => 1, 'product_type' => 9, 'special_code' => 104777, 'label' => str_repeat('Titre très long ', 20), 'desc' => '')));
$selectors = getHtmlSelectTitle($object, true).'<br><br>'.getHtmlSelectFreeText().' '.dolGetButtonTitle('', $langs->trans('SubtotalFreeTextManage'), 'fa fa-plus-circle', '#', 'subtotal-manage-freetext').' '.$langs->trans('subtotalFreeTextOrDesc');
$conf->use_javascript_ajax = 0;
$fallbackSelector = getHtmlSelectFreeText();
$conf->use_javascript_ajax = 1;
if (strpos($fallbackSelector, '<select') === false || strpos($fallbackSelector, '<script') !== false || strpos($fallbackSelector, 'width:300px') === false) {
	throw new RuntimeException('Native free-text selector fallback changed');
}
$labels = array();
foreach (array('Yes','No','CloseWindow','Confirm','SubtotalFreeTextManage','SubtotalFreeTextDiscard','SubtotalFreeTextDeleteConfirm','SubtotalFreeTextLoadFailed') as $key) $labels[$key] = $langs->transnoentities($key);
$fixtures = array('form' => $blankForm, 'richForm' => $richForm, 'list' => $list, 'emptyList' => $emptyList, 'pageList' => $pageList, 'selectors' => $selectors,
	'config' => array('url' => '/fixture-api', 'element' => 'propal', 'elementid' => 1, 'token' => 'fixture', 'canManage' => true, 'labels' => $labels));
?><!doctype html><html lang="fr"><meta charset="utf-8"><title>Subtotal — fixture de recette</title>
<link rel="stylesheet" href="/_core/includes/jquery/css/base/jquery-ui.min.css">
<link rel="stylesheet" href="/_core/includes/jquery/plugins/select2/dist/css/select2.min.css">
<link rel="stylesheet" href="/_core/theme/common/fontawesome-5/css/all.min.css">
<style>body{font:14px Arial;margin:20px}.hideobject{display:none}.centpercent{width:100%}td,th{padding:7px;text-align:left}.oddeven{background:#fafafa}.liste_titre{background:#eee}.right{text-align:right}.div-table-responsive-no-min{overflow:auto}.error{background:#fee;padding:10px}.button{margin:5px}.ui-dialog-content{overflow:auto}.pagination{display:inline-block;padding:4px}.parentonrightofpage{display:inline-block;max-width:100%}textarea{max-width:100%}</style>
<h1>Recette isolée — Subtotal 4.1.0</h1><p>Composants natifs ; données et réponses Ajax simulées. Aucun accès à une instance ERP.</p>
<button id="open">Ouvrir l’insertion</button> <button id="run">Exécuter les scénarios</button> <label><input type="checkbox" id="rich"> Éditeur natif</label>
<pre id="results"></pre><div id="inserted"></div>
<script src="/_core/includes/jquery/js/jquery.min.js"></script><script src="/_core/includes/jquery/js/jquery-ui.min.js"></script>
<script src="/_core/includes/jquery/plugins/select2/dist/js/select2.full.min.js"></script>
<script>var CKEDITOR_BASEPATH='/_core/public/includes/ckeditor/ckeditor/';var ckeditorConfig='/_core/theme/eldy/ckeditor/config.js';var ckeditorFilebrowserBrowseUrl='';var ckeditorFilebrowserImageBrowseUrl='';var dol_escape_htmltag=function(s){return $('<div>').text(s).html();};</script>
<script src="/_core/public/includes/ckeditor/ckeditor/ckeditor.js"></script>
<script src="/subtotal/js/freetext.js"></script>
<div class="fiche"><div class="tabsAction"><a href="#" class="butAction">Action native d’exemple</a></div></div>
<?php
$conf->global->SUBTOTAL_ALLOW_ADD_BLOCK = 1;
$conf->global->SUBTOTAL_ALLOW_ADD_LINE_UNDER_TITLE = 1;
require_once __DIR__.'/../class/actions_subtotal.class.php';
$hooks = new ActionsSubtotal($db);
$user->socid = 1;
ob_start();
$externalResult = $hooks->printNewFormat($object, $conf, $langs, 'id');
$externalOutput = ob_get_clean();
if ($externalResult !== false || $externalOutput !== '') throw new RuntimeException('External users must not receive the insertion dialog');
$user->socid = 0;
$hooks->printNewFormat($object, $conf, $langs, 'id');
?>
<script>var fixtures=<?php echo json_encode($fixtures, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="/subtotal/test/freetext_ui_test.js"></script></html>
