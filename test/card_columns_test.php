<?php
/**
 * Regression tests for subtotal card columns on situation invoices.
 *
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * Run with: php test/card_columns_test.php
 */

define('DOL_DOCUMENT_ROOT', __DIR__.'/stubs');
define('DOL_VERSION', '23.0.2');

$testSituationMode = 2;

function dol_include_once($path)
{
	return true;
}

function getDolGlobalInt($key, $default = 0)
{
	global $testSituationMode;

	return $key === 'INVOICE_USE_SITUATION' ? $testSituationMode : $default;
}

class Facture
{
	const TYPE_SITUATION = 5;
}

function assertSameValue($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException($message.': expected '.var_export($expected, true).', got '.var_export($actual, true));
	}
}

require_once __DIR__.'/../class/actions_subtotal.class.php';

$reflection = new ReflectionClass(ActionsSubtotal::class);
$actions = $reflection->newInstanceWithoutConstructor();
$columnCountMethod = $reflection->getMethod('getSituationInvoiceColumnCount');
$columnCountMethod->setAccessible(true);

$invoice = new stdClass();
$invoice->element = 'facture';
$invoice->type = Facture::TYPE_SITUATION;
$invoice->status = 1;
$invoice->statut = 1;
$invoice->situation_cycle_ref = 'SITUATION-001';

// A validated invoice in non-cumulative mode displays:
// cumulative progression, current-period progression and total at 100%.
$testSituationMode = 2;
assertSameValue(
	3,
	$columnCountMethod->invoke($actions, $invoice),
	'Unexpected situation column count on a validated non-cumulative invoice'
);

// Cumulative mode omits only the current-period progression column.
$testSituationMode = 1;
assertSameValue(
	2,
	$columnCountMethod->invoke($actions, $invoice),
	'Unexpected situation column count on a validated cumulative invoice'
);

// Draft and validated invoices use the same table structure once attached to a cycle.
$invoice->status = 0;
$invoice->statut = 0;
$testSituationMode = 2;
assertSameValue(
	3,
	$columnCountMethod->invoke($actions, $invoice),
	'Draft and validated situation invoices must reserve the same columns'
);

// A regular invoice or an invoice not yet attached to a situation cycle has no situation columns.
$invoice->situation_cycle_ref = '';
assertSameValue(
	0,
	$columnCountMethod->invoke($actions, $invoice),
	'Regular invoices must not reserve situation columns'
);

$invoice->element = 'commande';
$invoice->situation_cycle_ref = 'SITUATION-001';
assertSameValue(
	0,
	$columnCountMethod->invoke($actions, $invoice),
	'Non-invoice objects must not reserve situation columns'
);

print "OK\n";
