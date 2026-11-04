<?php
/**
 * Regression test for subtotal margins on situation invoices.
 *
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * Run with: php test/situation_margin_test.php /path/to/dolibarr/htdocs
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
	return $default;
}

function dol_include_once($path)
{
	return true;
}

function price2num($amount, $mode = '')
{
	return (float) $amount;
}

class Facture
{
	const TYPE_CREDIT_NOTE = 2;
	const TYPE_SITUATION = 5;
	public $element = 'facture';
	public $type = self::TYPE_SITUATION;
	public $situation_cycle_ref = 'SITUATION-001';
	public $lines = array();
}

function assertSameValue($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException($message.': expected '.var_export($expected, true).', got '.var_export($actual, true));
	}
}

require_once __DIR__.'/../class/subtotal.class.php';
require_once __DIR__.'/../class/actions_subtotal.class.php';

$line1 = (object) array(
	'id' => 11,
	'rang' => 2,
	'product_type' => 0,
	'fk_product_type' => 0,
	'qty' => 10,
	'pa_ht' => 50.0,
	'total_ht' => 300.0,
	'situation_percent' => 50.0,
);
$line2 = (object) array(
	'id' => 12,
	'rang' => 3,
	'product_type' => 0,
	'fk_product_type' => 0,
	'qty' => 4,
	'pa_ht' => 25.0,
	'total_ht' => 80.0,
	'situation_percent' => 25.0,
);
$titleLine = (object) array('id' => 10, 'rang' => 1, 'product_type' => 9, 'special_code' => TSubtotal::$module_number, 'qty' => 1);
$subtotalLine = (object) array('id' => 13, 'rang' => 4, 'product_type' => 9, 'special_code' => TSubtotal::$module_number, 'qty' => 99);

$invoice = new Facture();
$invoice->lines = array($titleLine, $line1, $line2, $subtotalLine);

$reflection = new ReflectionClass(ActionsSubtotal::class);
$actions = $reflection->newInstanceWithoutConstructor();
$marginMethod = $reflection->getMethod('getSubtotalMarginInfos');
$marginMethod->setAccessible(true);

// Revenue is already prorated in total_ht. Cost must use each line's own progress.
assertSameValue(
	105.0,
	$marginMethod->invoke($actions, $invoice, $subtotalLine)['margin'],
	'Each situation line must contribute its individually prorated margin'
);
assertSameValue(275.0, $marginMethod->invoke($actions, $invoice, $subtotalLine)['cost'], 'Situation cost must use each line progress');

// A regular invoice keeps the full cost of every line.
$invoice->type = 0;
assertSameValue(
	-220.0,
	$marginMethod->invoke($actions, $invoice, $subtotalLine)['margin'],
	'Regular invoice margin must not prorate costs'
);
assertSameValue(600.0, $marginMethod->invoke($actions, $invoice, $subtotalLine)['cost'], 'Regular invoice cost must remain unprorated');

print "OK\n";
