<?php
/**
 * Regression tests for PDF subtotal rendering without financial line mutations.
 *
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * Run with: php test/pdf_total_mutation_test.php
 */

define('DOL_DOCUMENT_ROOT', __DIR__.'/stubs');
define('DOL_VERSION', '23.0.2');

$testHideInnerLines = 0;
$testPdfTotals = array(100.0, 20.0, 120.0, array('20.000' => 20.0), 1.0);
$testGlobalStrings = array();

function dol_include_once($path)
{
	return true;
}

function GETPOST($key, $type = '')
{
	global $testHideInnerLines;

	return $key === 'hideInnerLines' ? $testHideInnerLines : 0;
}

function getDolGlobalInt($key, $default = 0)
{
	return $default;
}

function getDolGlobalString($key, $default = '')
{
	global $testGlobalStrings;

	return array_key_exists($key, $testGlobalStrings) ? $testGlobalStrings[$key] : $default;
}

function price($amount, $html = 0, $outputlangs = '', $trunc = 1, $rounding = -1, $nbdecimal = -1)
{
	return (string) ((float) $amount);
}

class TSubtotal
{
	public static $module_number = 104777;

	public static function showQtyForObject($object)
	{
		return false;
	}

	public static function isSubtotal($line)
	{
		return self::isModSubtotalLine($line) && (float) $line->qty >= 90;
	}

	public static function isModSubtotalLine($line)
	{
		return (int) $line->product_type === 9 && (int) $line->special_code === 104777;
	}

	public static function getNiveau($line)
	{
		return 1;
	}

	public static function getParentTitleOfLine($object, $rang, $level = 0)
	{
		return false;
	}

	public static function getAllTitleFromLine($line)
	{
		return array();
	}
}

class TestPdf
{
	public $page_largeur = 210;
	public $marge_droite = 10;
	public $postotalht = 160;
	public $printedValues = array();

	protected $bMargin = 10;

	public function AcceptPageBreak()
	{
		return true;
	}

	public function SetAutoPageBreak($enabled, $margin = 0)
	{
	}

	public function SetFillColor($red, $green, $blue)
	{
	}

	public function SetFont($family, $style = '', $size = 0)
	{
	}

	public function getCellPaddings()
	{
		return array('L' => 0, 'T' => 0, 'R' => 0, 'B' => 0);
	}

	public function setCellPaddings($left, $top, $right, $bottom)
	{
	}

	public function writeHTMLCell($width, $height, $x, $y, $html)
	{
	}

	public function getPage()
	{
		return 1;
	}

	public function getStringHeight($width, $text)
	{
		return 4;
	}

	public function SetXY($x, $y)
	{
	}

	public function MultiCell($width, $height, $text)
	{
		if ($text !== '') {
			$this->printedValues[] = (string) $text;
		}
	}

	public function setColor($type, $red, $green, $blue)
	{
	}
}

class TestLangs
{
	public function trans($key)
	{
		return $key;
	}

	public function transnoentitiesnoconv($key)
	{
		return $key;
	}
}

function assertSameValue($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException($message.': expected '.var_export($expected, true).', got '.var_export($actual, true));
	}
}

function createTechnicalLine($id, $totalHt, $totalVat, $totalTtc)
{
	$line = new stdClass();
	$line->id = $id;
	$line->rowid = $id;
	$line->rang = $id;
	$line->qty = 99;
	$line->product_type = 9;
	$line->special_code = 104777;
	$line->total_ht = $totalHt;
	$line->total_tva = $totalVat;
	$line->total = $totalHt;
	$line->total_ttc = $totalTtc;
	$line->multicurrency_total_ht = 0.0;
	$line->multicurrency_total_tva = 0.0;
	$line->multicurrency_total_ttc = 0.0;
	$line->array_options = array();

	return $line;
}

function createPdfObject($lines)
{
	$object = new stdClass();
	$object->id = 123;
	$object->element = 'facture';
	$object->db = null;
	$object->context = array();
	$object->context['subtotalPdfModelInfo'] = new stdClass();
	$object->context['subtotalPdfModelInfo']->cols = false;
	$object->lines = $lines;

	return $object;
}

require_once __DIR__.'/../class/actions_subtotal.class.php';

class TestActionsSubtotal extends ActionsSubtotal
{
	public function getTotalLineFromObject(&$object, &$line, $use_level = false, $return_all = 0)
	{
		global $testPdfTotals;

		return $return_all ? $testPdfTotals : $testPdfTotals[0];
	}
}

$reflection = new ReflectionClass(TestActionsSubtotal::class);
$actions = $reflection->newInstanceWithoutConstructor();

$conf = new stdClass();
$conf->global = new stdClass();
$langs = new TestLangs();
$user = new stdClass();
$db = null;
$subtotal_last_title_posy = null;

// Standard rendering must display the calculated subtotal without changing the line.
$technicalLine = createTechnicalLine(10, 1.0, 2.0, 3.0);
$object = createPdfObject(array($technicalLine));
$pdf = new TestPdf();
$testHideInnerLines = 0;
$action = '';
$_SESSION = array(
	'subtotal_hideInnerLines_facture' => array($object->id => array()),
	'subtotal_hidedetails_facture' => array($object->id => array()),
	'subtotal_hideprices_facture' => array($object->id => array()),
);

$actions->doActions(array('context' => 'invoicecard'), $object, 'builddoc', null);
$actions->pdf_add_total($pdf, $object, $technicalLine, 'Subtotal', '', 10, 10, 100, 4);

assertSameValue(1.0, $technicalLine->total_ht, 'Standard rendering changed total_ht');
assertSameValue(2.0, $technicalLine->total_tva, 'Standard rendering changed total_tva');
assertSameValue(1.0, $technicalLine->total, 'Standard rendering changed total');
assertSameValue(3.0, $technicalLine->total_ttc, 'Standard rendering changed total_ttc');
assertSameValue(true, in_array('100', $pdf->printedValues, true), 'Standard rendering did not display the calculated subtotal');

// Hidden inner lines must keep zero financial totals on source and PDF technical lines.
$productLine = new stdClass();
$productLine->id = 20;
$productLine->rowid = 20;
$productLine->rang = 1;
$productLine->qty = 1;
$productLine->product_type = 0;
$productLine->special_code = 0;
$productLine->total_ht = 100.0;
$productLine->total_tva = 20.0;
$productLine->total = 100.0;
$productLine->total_ttc = 120.0;
$productLine->array_options = array();

$technicalLine = createTechnicalLine(21, 0.0, 0.0, 0.0);
$technicalLine->rang = 2;
$object = createPdfObject(array($productLine, $technicalLine));
$pdf = new TestPdf();
$testHideInnerLines = 1;

$actions->beforePDFCreation(array('i' => 0), $object, $action);

assertSameValue(0.0, $technicalLine->total_ht, 'Hidden-lines preparation changed source total_ht');
assertSameValue(0.0, $technicalLine->total_tva, 'Hidden-lines preparation changed source total_tva');
assertSameValue(0.0, $technicalLine->total, 'Hidden-lines preparation changed source total');
assertSameValue(0.0, $technicalLine->total_ttc, 'Hidden-lines preparation changed source total_ttc');
assertSameValue(1, count($object->lines), 'Hidden-lines preparation kept unexpected detail lines');
assertSameValue(0.0, $object->lines[0]->total_ht, 'PDF technical line contains a facturable total_ht');
assertSameValue(0.0, $object->lines[0]->total_tva, 'PDF technical line contains a facturable total_tva');
assertSameValue(0.0, $object->lines[0]->total, 'PDF technical line contains a facturable total');
assertSameValue(0.0, $object->lines[0]->total_ttc, 'PDF technical line contains a facturable total_ttc');
assertSameValue(array('20.000' => 20.0), $object->lines[0]->TTotal_tva, 'Hidden-lines preparation lost the VAT breakdown');

$actions->pdf_add_total($pdf, $object, $object->lines[0], 'Subtotal', '', 10, 10, 100, 4);

assertSameValue(true, in_array('100', $pdf->printedValues, true), 'Hidden-lines rendering did not display the cached subtotal');
assertSameValue(0.0, $object->lines[0]->total_ht, 'Hidden-lines rendering changed total_ht');
assertSameValue(0.0, $object->lines[0]->total_tva, 'Hidden-lines rendering changed total_tva');
assertSameValue(0.0, $object->lines[0]->total, 'Hidden-lines rendering changed total');
assertSameValue(0.0, $object->lines[0]->total_ttc, 'Hidden-lines rendering changed total_ttc');

// VAT replacement lines must carry every amount once while the technical line stays neutral.
$productLine20 = clone $productLine;
$productLine20->id = 30;
$productLine20->rowid = 30;
$productLine20->rang = 1;
$productLine20->tva_tx = '20.000';
$productLine20->total_ht = 60.0;
$productLine20->total_tva = 12.0;
$productLine20->total = 60.0;
$productLine20->total_ttc = 72.0;
$productLine20->multicurrency_total_ht = 66.0;
$productLine20->multicurrency_total_tva = 13.2;
$productLine20->multicurrency_total_ttc = 79.2;

$productLine10 = clone $productLine;
$productLine10->id = 31;
$productLine10->rowid = 31;
$productLine10->rang = 2;
$productLine10->tva_tx = '10.000';
$productLine10->total_ht = 40.0;
$productLine10->total_tva = 4.0;
$productLine10->total = 40.0;
$productLine10->total_ttc = 44.0;
$productLine10->multicurrency_total_ht = 44.0;
$productLine10->multicurrency_total_tva = 4.4;
$productLine10->multicurrency_total_ttc = 48.4;

$technicalLine = createTechnicalLine(32, 0.0, 0.0, 0.0);
$technicalLine->rang = 3;
$object = createPdfObject(array($productLine20, $productLine10, $technicalLine));
$testPdfTotals = array(100.0, 16.0, 116.0, array('20.000' => 12.0, '10.000' => 4.0), 2.0);
$testGlobalStrings['SUBTOTAL_REPLACE_WITH_VAT_IF_HIDE_INNERLINES'] = '1';

$actions->beforePDFCreation(array('i' => 0), $object, $action);

assertSameValue(3, count($object->lines), 'VAT replacement did not create the expected PDF lines');

$facturableTotals = array(
	'total_ht' => 0.0,
	'total_tva' => 0.0,
	'total_ttc' => 0.0,
	'multicurrency_total_ht' => 0.0,
	'multicurrency_total_tva' => 0.0,
	'multicurrency_total_ttc' => 0.0,
);
$pdfTechnicalLine = null;
foreach ($object->lines as $pdfLine) {
	if ((int) $pdfLine->product_type === 9) {
		$pdfTechnicalLine = $pdfLine;
		continue;
	}
	foreach (array_keys($facturableTotals) as $field) {
		$facturableTotals[$field] += (float) $pdfLine->{$field};
	}
}

assertSameValue(100.0, $facturableTotals['total_ht'], 'VAT replacement duplicated or lost total_ht');
assertSameValue(16.0, $facturableTotals['total_tva'], 'VAT replacement duplicated or lost total_tva');
assertSameValue(116.0, $facturableTotals['total_ttc'], 'VAT replacement duplicated or lost total_ttc');
assertSameValue(110.0, $facturableTotals['multicurrency_total_ht'], 'VAT replacement duplicated or lost multicurrency_total_ht');
assertSameValue(17.6, $facturableTotals['multicurrency_total_tva'], 'VAT replacement duplicated or lost multicurrency_total_tva');
assertSameValue(127.6, $facturableTotals['multicurrency_total_ttc'], 'VAT replacement duplicated or lost multicurrency_total_ttc');
assertSameValue(true, is_object($pdfTechnicalLine), 'VAT replacement removed the technical subtotal line');
assertSameValue(0.0, $pdfTechnicalLine->total_ht, 'VAT replacement made the technical total_ht facturable');
assertSameValue(0.0, $pdfTechnicalLine->total_tva, 'VAT replacement made the technical total_tva facturable');
assertSameValue(0.0, $pdfTechnicalLine->total, 'VAT replacement made the technical total facturable');
assertSameValue(0.0, $pdfTechnicalLine->total_ttc, 'VAT replacement made the technical total_ttc facturable');
assertSameValue(false, property_exists($pdfTechnicalLine, 'TTotal_tva'), 'VAT replacement duplicated VAT on the technical line');

print "OK\n";
