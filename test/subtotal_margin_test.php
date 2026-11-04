<?php
/**
 * Regression tests for margins using real Subtotal block selection.
 * Run with: php test/subtotal_margin_test.php /path/to/dolibarr/htdocs
 *
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

define('DOL_DOCUMENT_ROOT', __DIR__.'/stubs');
define('DOL_VERSION', '23.0.2');

$coreRoot = isset($argv[1]) ? realpath($argv[1]) : false;
if ($coreRoot === false || !is_file($coreRoot.'/core/class/html.formmargin.class.php')) {
	fwrite(STDERR, "Pass the path to a Dolibarr htdocs directory.\n");
	exit(1);
}
require_once $coreRoot.'/core/class/html.formmargin.class.php';
$conf = (object) array('global' => new stdClass());

function getDolGlobalInt($key, $default = 0)
{
	global $conf;
	return isset($conf->global->{$key}) ? (int) $conf->global->{$key} : $default;
}

function dol_include_once($path)
{
	return true;
}

function price2num($amount, $mode = '')
{
	return (float) $amount;
}

// The document line cost must remain authoritative, even when zero or negative.
class Product
{
	public function __construct($db)
	{
		throw new RuntimeException('Subtotal margins must not reload catalogue costs');
	}
}

class MarginTestDocument
{
	const TYPE_CREDIT_NOTE = 2;
	const TYPE_SITUATION = 5;
	public $element;
	public $type = 0;
	public $lines;
	public $id = 1;
	public $statut = 1;
	public $status = 1;
}

require_once __DIR__.'/../class/subtotal.class.php';
require_once __DIR__.'/../class/actions_subtotal.class.php';

/** @return stdClass */
function marginTestLine($id, $qty, $total = 0.0, $cost = 0.0, $technical = false)
{
	return (object) array(
		'id' => $id, 'rang' => $id, 'qty' => $qty,
		'product_type' => $technical ? 9 : 0,
		'special_code' => $technical ? TSubtotal::$module_number : 0,
		'total_ht' => $total, 'pa_ht' => $cost, 'fk_product' => 42, 'fk_product_type' => 0,
		'subprice' => $qty ? $total / $qty : 0.0, 'remise_percent' => 0,
		'label' => '', 'description' => '', 'desc' => '', 'info_bits' => 0, 'array_options' => array(),
		'tva_tx' => 0, 'total_tva' => 0, 'total_ttc' => $total,
	);
}

$reflection = new ReflectionClass(ActionsSubtotal::class);
$actions = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('getSubtotalMarginInfos');
$method->setAccessible(true);
$failures = array();

$cases = array(
	// Totals from the reported quotation, with a 50% margin rate on cost.
	'two independent blocks' => array(
		array(
			marginTestLine(1, 1, 0, 0, true), marginTestLine(2, 2, 14083.50, 4694.50),
			marginTestLine(3, 99, 0, 0, true), marginTestLine(4, 1, 0, 0, true),
			marginTestLine(5, 2, 7765.50, 2588.50), marginTestLine(6, 99, 0, 0, true),
		), array(2 => 4694.50, 5 => 2588.50),
	),
	'parent subtotal closes an open child' => array(
		array(
			marginTestLine(1, 1, 0, 0, true), marginTestLine(2, 1, 100, 60),
			marginTestLine(3, 2, 0, 0, true), marginTestLine(4, 1, 200, 100),
			marginTestLine(5, 99, 0, 0, true), marginTestLine(6, 1, 0, 0, true),
			marginTestLine(7, 1, 999, 900), marginTestLine(8, 99, 0, 0, true),
		), array(4 => 140.0, 7 => 99.0),
	),
	'nested subtotals do not double count' => array(
		array(
			marginTestLine(1, 1, 0, 0, true), marginTestLine(2, 1, 100, 60),
			marginTestLine(3, 2, 0, 0, true), marginTestLine(4, 1, 200, 100),
			marginTestLine(5, 98, 200, 100, true), marginTestLine(6, 99, 300, 160, true),
		), array(4 => 100.0, 5 => 140.0),
	),
	'only lines preceding the requested subtotal contribute' => array(
		array(
			marginTestLine(1, 1, 0, 0, true), marginTestLine(2, 1, 100, 60),
			marginTestLine(3, 99, 0, 0, true), marginTestLine(4, 1, 200, 100),
			marginTestLine(5, 99, 0, 0, true),
		), array(2 => 40.0),
	),
	'explicit zero cost and negative selling price' => array(
		array(
			marginTestLine(1, 1, 0, 0, true), marginTestLine(2, 2, 100, 0),
			marginTestLine(3, 2, -40, 10), marginTestLine(4, 99, 0, 0, true),
		), array(3 => 80.0),
	),
	'subtotal without a title matches the amount range' => array(
		array(marginTestLine(1, 2, 100, 30), marginTestLine(2, 99, 0, 0, true)),
		array(1 => 40.0),
	),
	'empty block' => array(
		array(marginTestLine(1, 1, 0, 0, true), marginTestLine(2, 99, 0, 0, true)),
		array(1 => 0.0),
	),
);

$serviceLine = marginTestLine(3, 2, 300, 100);
$serviceLine->product_type = 1;
$cases['products, services, free text and discounted revenue'] = array(
	array(
		marginTestLine(1, 1, 0, 0, true), marginTestLine(2, 3, 270, 60), $serviceLine,
		marginTestLine(4, 50, 999, 10, true), marginTestLine(5, 99, 0, 0, true),
	), array(4 => 190.0),
);

foreach (array('propal', 'commande', 'facture') as $element) {
	foreach ($cases as $label => $case) {
		$document = new MarginTestDocument();
		$document->element = $element;
		$document->lines = $case[0];
		$before = serialize($document);
		foreach ($case[1] as $index => $expected) {
			try {
				$infos = $method->invoke($actions, $document, $document->lines[$index]);
				$actual = $infos['margin'];
				if (abs($actual - $expected) > 0.000001) {
					$failures[] = $element.' / '.$label.': expected '.$expected.', got '.$actual;
				}
			} catch (RuntimeException $error) {
				$failures[] = $element.' / '.$label.': '.$error->getMessage();
			}
		}
		if (serialize($document) !== $before) {
			$failures[] = $element.' / '.$label.': rendering changed document data';
		}
	}
}

// Native missing-cost policy must work on a copy, without modifying document data.
$conf->global->ForceBuyingPriceIfNull = 1;
$missingCostLine = marginTestLine(2, 2, 180, null);
$missingCostLine->subprice = 100;
$missingCostLine->remise_percent = 10;
$document = new MarginTestDocument();
$document->element = 'propal';
$document->lines = array(marginTestLine(1, 1, 0, 0, true), $missingCostLine, marginTestLine(3, 99, 0, 0, true));
$before = serialize($document);
$infos = $method->invoke($actions, $document, $document->lines[2]);
if ($infos['margin'] != 0.0 || $infos['cost'] != 180.0 || serialize($document) !== $before) {
	$failures[] = 'Native missing-cost policy must leave the original line unchanged';
}

if ($failures) {
	fwrite(STDERR, implode("\n", $failures)."\n");
	exit(1);
}
print "OK: subtotal margins on proposals, orders and invoices\n";
