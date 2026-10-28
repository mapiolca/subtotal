<?php
/**
 * Regression test for subtotal margins on situation invoices.
 *
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * Run with: php test/situation_margin_test.php
 */

define('DOL_DOCUMENT_ROOT', __DIR__.'/stubs');
define('DOL_VERSION', '23.0.2');

$testProductLines = array();

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
	const TYPE_SITUATION = 5;
}

class TSubtotal
{
	public static function getParentTitleOfLine($object, $rang)
	{
		return (object) array('id' => 10);
	}

	public static function getLinesFromTitleId($object, $titleId)
	{
		global $testProductLines;

		return $testProductLines;
	}

	public static function isModSubtotalLine($line)
	{
		return isset($line->product_type) && (int) $line->product_type === 9;
	}
}

function assertSameValue($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException($message.': expected '.var_export($expected, true).', got '.var_export($actual, true));
	}
}

require_once __DIR__.'/../class/actions_subtotal.class.php';

$line1 = (object) array(
	'product_type' => 0,
	'qty' => 10,
	'pa_ht' => 50.0,
	'total_ht' => 300.0,
	'situation_percent' => 50.0,
);
$line2 = (object) array(
	'product_type' => 0,
	'qty' => 4,
	'pa_ht' => 25.0,
	'total_ht' => 80.0,
	'situation_percent' => 25.0,
);
$testProductLines = array($line1, $line2);

$invoice = (object) array(
	'element' => 'facture',
	'type' => Facture::TYPE_SITUATION,
	'situation_cycle_ref' => 'SITUATION-001',
);
$subtotalLine = (object) array('rang' => 3);

$reflection = new ReflectionClass(ActionsSubtotal::class);
$actions = $reflection->newInstanceWithoutConstructor();
$marginMethod = $reflection->getMethod('getSubtotalMargin');
$marginMethod->setAccessible(true);

// Revenue is already prorated in total_ht. Cost must use each line's own progress.
assertSameValue(
	105.0,
	$marginMethod->invoke($actions, $invoice, $subtotalLine),
	'Each situation line must contribute its individually prorated margin'
);

// A regular invoice keeps the full cost of every line.
$invoice->type = 0;
assertSameValue(
	-220.0,
	$marginMethod->invoke($actions, $invoice, $subtotalLine),
	'Regular invoice margin must not prorate costs'
);

print "OK\n";
