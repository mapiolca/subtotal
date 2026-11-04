<?php
/**
 * Render the actual card hook with simulated page context and native margin sources.
 * Run: php test/subtotal_summary_test.php /path/to/dolibarr/htdocs [--html]
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

ob_start();
require __DIR__.'/subtotal_margin_test.php';
ob_end_clean();
require_once __DIR__.'/../lib/subtotal.lib.php';

function getDolGlobalString($key, $default = '')
{
	global $conf;
	return isset($conf->global->{$key}) ? (string) $conf->global->{$key} : $default;
}
function GETPOST($key, $type = '') { return ''; }
function newToken() { return 'test-token'; }
function isModEnabled($module) { return false; }
function price($amount) { return (string) $amount; }
function dol_escape_htmltag($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function dol_htmlentities($text, $flags = ENT_QUOTES) { return htmlspecialchars((string) $text, $flags, 'UTF-8'); }
function dol_string_nohtmltag($text) { return strip_tags($text); }
function img_picto($label, $icon) { return ''; }

class SubtotalAccess
{
	public static function canWrite($user, $object) { return false; }
	public static function isEditable($object) { return false; }
}
class SummaryTestHooks
{
	public function executeHooks($name, $parameters, $object, $action) { return 0; }
}
class SummaryTestTranslations
{
	public $translations = array();
	public function load($catalogue)
	{
		global $coreRoot;
		foreach (file($coreRoot.'/langs/fr_FR/'.$catalogue.'.lang', FILE_IGNORE_NEW_LINES) as $line) {
			if (preg_match('/^([A-Za-z0-9_]+)=(.*)$/', $line, $matches)) {
				$this->translations[$matches[1]] = $matches[2];
			}
		}
	}
	public function trans($key) { return isset($this->translations[$key]) ? $this->translations[$key] : $key; }
	public function transnoentities($key) { return html_entity_decode($this->trans($key), ENT_QUOTES, 'UTF-8'); }
}

$langs = new SummaryTestTranslations();
$langs->load('main');
$conf->global->SUBTOTAL_USE_NEW_FORMAT = 1;
$conf->global->SUBTOTAL_TITLE_STYLE = 'B';
$conf->currency = 'EUR';
$conf->browser = (object) array('phone' => false);
$user = new stdClass();
$hookmanager = new SummaryTestHooks();
$action = '';
$html = '';

foreach (array(true, false) as $withMargin) {
	$conf->global->DISPLAY_MARGIN_ON_SUBTOTALS = $withMargin ? 1 : 0;
	$document = new MarginTestDocument();
	$document->element = 'propal';
	$document->lines = $cases['two independent blocks'][0];
	$document->lines[0]->label = 'Lot <b>A</b> & "électricité"';
	$document->lines[3]->label = str_repeat('Titre long de section ', 10);
	$rows = '';
	foreach (array(2, 5) as $index) {
		$parameters = array('context' => 'propalcard', 'currentcontext' => 'propalcard', 'line' => $document->lines[$index], 'num' => 6, 'i' => $index, 'var' => true);
		ob_start();
		$actions->printObjectLine($parameters, $document, $action, $hookmanager);
		$rows .= ob_get_clean();
	}
	$dom = new DOMDocument();
	libxml_use_internal_errors(true);
	$dom->loadHTML('<?xml encoding="UTF-8"><table>'.$rows.'</table>');
	$xpath = new DOMXPath($dom);
	$text = preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $xpath->query('//tr')->item(0)->textContent));
	$expected = 'Sous-total : Lot A & "électricité" | '.($withMargin ? 'Prix de revient : 9389 | Marge : 4694.5 | ' : '').'Montant HT : 14083.5';
	if (trim($text) !== $expected || $xpath->query('//tr[1]/td')->length !== 5 || $xpath->query('//b')->length !== 0) {
		throw new RuntimeException('Unexpected subtotal summary: '.$text);
	}
	$html .= '<h2>'.($withMargin ? 'Marges affichées' : 'Marges masquées').'</h2><div style="overflow:auto"><table style="width:100%">'.$rows.'</table></div>';
}

if (in_array('--html', $argv, true)) {
	print '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Aperçu des sous-totaux</title><style>body{font:14px Arial;margin:24px}td{padding:12px 8px}table{border-collapse:collapse}h2{font-size:16px}.nowrap,.nowraponall{white-space:nowrap}</style><body>'.$html.'</body></html>';
} else {
	print "OK: actual subtotal card rows, native French labels, costs, order, escaped titles and hidden margins\n";
}
