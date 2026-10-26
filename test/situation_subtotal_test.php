<?php
/**
 * Regression tests for situation invoice subtotal calculations.
 *
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * Run with: php test/situation_subtotal_test.php
 */

define('DOL_VERSION', '23.0.2');

$testAction = 'none';
$testSituationMode = 2;

function GETPOST($key, $type = '')
{
	global $testAction;

	return $key === 'action' ? $testAction : '';
}

function getDolGlobalInt($key, $default = 0)
{
	global $testSituationMode;

	return $key === 'INVOICE_USE_SITUATION' ? $testSituationMode : $default;
}

function getDolGlobalString($key, $default = '')
{
	return $default;
}

function price2num($amount, $rounding = '')
{
	return (float) $amount;
}

function dol_include_once($path)
{
	return true;
}

class Facture
{
	const TYPE_SITUATION = 5;
}

class TSubtotal
{
	public static function isSubtotal($line)
	{
		return self::isModSubtotalLine($line) && (float) $line->qty >= 90;
	}

	public static function getNiveau($line)
	{
		return 1;
	}

	public static function getParentTitleOfLine($object, $rang, $level = 0)
	{
		return false;
	}

	public static function isModSubtotalLine($line)
	{
		return (int) $line->product_type === 9 && (int) $line->special_code === 104777;
	}
}

class TestSituationLine
{
	public $id;
	public $rang;
	public $qty;
	public $product_type = 0;
	public $special_code = 0;
	public $situation_percent;
	public $total_ht;
	public $total_tva;
	public $total_ttc;
	public $tva_tx = '20.000';
	public $previous_progress;

	public function __construct($id, $rang, $qty, $situationPercent, $totalHt, $totalTva, $totalTtc, $previousProgress = 0)
	{
		$this->id = $id;
		$this->rang = $rang;
		$this->qty = $qty;
		$this->situation_percent = $situationPercent;
		$this->total_ht = $totalHt;
		$this->total_tva = $totalTva;
		$this->total_ttc = $totalTtc;
		$this->previous_progress = $previousProgress;
	}

	public function get_prev_progress($invoiceId)
	{
		return $this->previous_progress;
	}

	public function getSituationRatio()
	{
		if (getDolGlobalInt('INVOICE_USE_SITUATION') === 1) {
			return ($this->situation_percent - $this->previous_progress) / $this->situation_percent;
		}

		return 1.0;
	}
}

class TestLegacySituationLine
{
	public $id;
	public $rang;
	public $qty;
	public $product_type = 0;
	public $special_code = 0;
	public $situation_percent;
	public $total_ht;
	public $total_tva;
	public $total_ttc;
	public $tva_tx = '20.000';
	public $previous_progress;

	public function __construct($id, $rang, $qty, $situationPercent, $totalHt, $totalTva, $totalTtc, $previousProgress = 0)
	{
		$this->id = $id;
		$this->rang = $rang;
		$this->qty = $qty;
		$this->situation_percent = $situationPercent;
		$this->total_ht = $totalHt;
		$this->total_tva = $totalTva;
		$this->total_ttc = $totalTtc;
		$this->previous_progress = $previousProgress;
	}

	public function get_prev_progress($invoiceId)
	{
		return $this->previous_progress;
	}
}

class TestSubtotalLine
{
	public $id = 99;
	public $rang = 99;
	public $qty = 99;
	public $product_type = 9;
	public $special_code = 104777;
}

function assertAmount($expected, $actual, $message)
{
	if (abs((float) $expected - (float) $actual) > 0.00001) {
		throw new RuntimeException($message.': expected '.$expected.', got '.$actual);
	}
}

require_once __DIR__.'/../lib/subtotal.lib.php';

$subtotalLine = new TestSubtotalLine();
$invoice = new stdClass();
$invoice->id = 123;
$invoice->element = 'facture';
$invoice->type = Facture::TYPE_SITUATION;

// New mode: totals already contain the current-period delta and must not be reduced again.
$testSituationMode = 2;
$invoice->lines = array(
	new TestSituationLine(1, 1, 1, 25, 1082.25, 216.45, 1298.70, 70),
	new TestSituationLine(2, 2, 1, 95, 15038.00, 3007.60, 18045.60, 0),
	$subtotalLine,
);

$testAction = 'none';
$beforeBuildDoc = getTotalLineFromObject($invoice, $subtotalLine);
$testAction = 'builddoc';
$afterBuildDoc = getTotalLineFromObject($invoice, $subtotalLine);
$allTotals = getTotalLineFromObject($invoice, $subtotalLine, false, 1);

assertAmount(16120.25, $beforeBuildDoc, 'Unexpected subtotal before document generation');
assertAmount(16120.25, $afterBuildDoc, 'Subtotal changed during document generation');
assertAmount(3224.05, $allTotals[1], 'Unexpected VAT subtotal in non-cumulative mode');
assertAmount(19344.30, $allTotals[2], 'Unexpected including-tax subtotal in non-cumulative mode');
assertAmount(2, $allTotals[4], 'Unexpected subtotal quantity');

// Legacy mode: cumulative totals must be converted to the current-period delta.
$testSituationMode = 1;
$invoice->lines = array(
	new TestSituationLine(3, 1, 1, 95, 4112.55, 822.51, 4935.06, 70),
	$subtotalLine,
);
$testAction = 'none';
$legacyCardTotal = getTotalLineFromObject($invoice, $subtotalLine);
$testAction = 'builddoc';
$legacyTotals = getTotalLineFromObject($invoice, $subtotalLine, false, 1);

assertAmount(4112.55, $legacyCardTotal, 'Legacy card subtotal behavior changed');
assertAmount(1082.25, $legacyTotals[0], 'Unexpected legacy-mode subtotal');
assertAmount(216.45, $legacyTotals[1], 'Unexpected legacy-mode VAT subtotal');
assertAmount(1298.70, $legacyTotals[2], 'Unexpected legacy-mode including-tax subtotal');

// Dolibarr 20-22 compatibility path, before getSituationRatio() was available.
$invoice->lines = array(
	new TestLegacySituationLine(4, 1, 1, 95, 4112.55, 822.51, 4935.06, 70),
	$subtotalLine,
);
$legacyFallbackTotal = getTotalLineFromObject($invoice, $subtotalLine);
assertAmount(1082.25, $legacyFallbackTotal, 'Unexpected legacy compatibility subtotal');

print "OK\n";
