<?php
/**
 * Exercise title pagination with real TCPDF and simulated Dolibarr services.
 * Run: php test/pdf_title_pagination_test.php /path/to/tcpdf.php [output-directory]
 *
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}
$tcpdfPath = isset($argv[1]) ? realpath($argv[1]) : false;
if ($tcpdfPath === false || !is_file($tcpdfPath)) {
	fwrite(STDERR, "Pass the path to a native tcpdf.php file.\n");
	exit(1);
}
$outputDirectory = isset($argv[2]) ? realpath($argv[2]) : null;
if (isset($argv[2]) && ($outputDirectory === false || !is_dir($outputDirectory))) {
	fwrite(STDERR, "The optional output directory must exist.\n");
	exit(1);
}
define('DOL_DOCUMENT_ROOT', __DIR__.'/stubs');
error_reporting(E_ALL & ~E_DEPRECATED);

$settings = array();
function dol_include_once($path)
{
	return true;
}
function GETPOSTISSET($key)
{
	return false;
}
function GETPOST($key, $type = '')
{
	return 0;
}
function getDolGlobalInt($key, $default = 0)
{
	global $settings;
	return isset($settings[$key]) ? (int) $settings[$key] : $default;
}
function getDolGlobalString($key, $default = '')
{
	global $settings;
	return isset($settings[$key]) ? (string) $settings[$key] : $default;
}
function colorValidateHex($value)
{
	return $value === '#d0e8fb';
}
function colorStringToArray($value, $default)
{
	return array(208, 232, 251);
}
function dol_html_entity_decode($value, $flags)
{
	return html_entity_decode($value, $flags, 'UTF-8');
}

require_once $tcpdfPath;
require_once __DIR__.'/../class/actions_subtotal.class.php';

/** Record the native fill geometry without replacing TCPDF pagination. */
class TitlePaginationPdf extends TCPDF
{
	public $page_largeur = 210;
	public $marge_droite = 10;
	/** @var array<int, array{page: int, x: float, y: float, width: float, height: float}> */
	public $backgrounds = array();

	protected function getCellCode($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'M')
	{
		if ($fill && $txt === '') {
			$this->backgrounds[] = array('page' => $this->getPage(), 'x' => $this->GetX(), 'y' => $this->GetY(), 'width' => (float) $w, 'height' => (float) $h);
		}
		return parent::getCellCode($w, $h, $txt, $border, $ln, $align, $fill, $link, $stretch, $ignore_min_height, $calign, $valign);
	}
}

function checkPagination($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$actions = (new ReflectionClass('ActionsSubtotal'))->newInstanceWithoutConstructor();
$caseCount = 0;
foreach (array(70, 270.5) as $startY) {
	foreach (array(false, true) as $background) {
		foreach (array(false, true) as $forced) {
			foreach (array(false, true) as $html) {
				foreach (array(false, true) as $longDescription) {
					$settings = $background ? array('SUBTOTAL_TITLE_BACKGROUNDCOLOR' => '#d0e8fb') : array();
					$label = $forced ? "VARIANTE SYSTEME D'INTEGRATION" : 'ETUDES & GESTION ADMINISTRATIVE';
					$line = (object) array(
						'id' => 1, 'label' => $html ? '<b>'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</b>' : $label,
						'desc' => $longDescription ? str_repeat('Description du titre<br>', 65) : '', 'description' => '',
						'qty' => 1, 'info_bits' => $forced ? 8 : 0, 'product_type' => 9, 'special_code' => 104777,
					);
					$object = (object) array('element' => 'propal', 'id' => 1, 'lines' => array($line), 'context' => array(
						'subtotalPdfModelInfo' => (object) array('cols' => array('desc' => array()), 'marge_gauche' => 10, 'marge_droite' => 10, 'page_largeur' => 210),
					));
					$outputlangs = new class {
						public function convToOutputCharset($text) { return $text; }
					};
					$pdf = new TitlePaginationPdf();
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
					$pdf->SetMargins(10, 42, 10);
					$pdf->SetAutoPageBreak(true, 22);
					$pdf->AddPage();
					$pdf->SetFont('helvetica', '', 9);
					$pdf->SetXY(11, $startY);
					$paddings = $pdf->getCellPaddings();
					$parameters = array('outputlangs' => $outputlangs, 'i' => 0, 'posx' => 11, 'posy' => $startY, 'w' => 128, 'h' => 3);
					$action = '';
					// The document model owns this transaction, including forced page breaks.
					$pdf->startTransaction();
					checkPagination($actions->pdf_writelinedesc($parameters, $object, $action) === 1, 'Title hook was not handled.');
					$case = 'y='.$startY.' background='.(int) $background.' forced='.(int) $forced.' html='.(int) $html.' description='.(int) $longDescription;
					$titlePage = $forced || $startY > 260 ? 2 : 1;
					$expectedPages = $titlePage + (int) $longDescription;
					checkPagination($pdf->getNumPages() === $expectedPages, 'Unexpected page count: '.$case);
					checkPagination($pdf->getPage() === $expectedPages && $pdf->GetY() < 200, 'Wrong continuation position: '.$case);
					checkPagination($pdf->getAutoPageBreak() && $pdf->getBreakMargin() === 22, 'Page-break settings changed: '.$case);
					checkPagination($pdf->getCellPaddings() === $paddings, 'Cell padding changed: '.$case);
					if ($background) {
						checkPagination(count($pdf->backgrounds) === 1 && $pdf->backgrounds[0]['page'] === $titlePage, 'Background is not on the title page: '.$case);
						checkPagination(abs($pdf->backgrounds[0]['y'] - ($titlePage === 2 ? 42 : $startY)) < 0.01, 'Background retained the old Y: '.$case);
						checkPagination($pdf->backgrounds[0]['x'] === 10.0 && $pdf->backgrounds[0]['width'] === 190.0, 'Model column width changed: '.$case);
					}
					$pdf->rollbackTransaction(true);
					checkPagination($pdf->getNumPages() === 1 && abs($pdf->GetY() - $startY) < 0.01, 'Caller transaction was lost: '.$case);
					$actions->pdf_writelinedesc($parameters, $object, $action);
					$pdf->SetFont('helvetica', '', 9);
					$pdf->MultiCell(128, 5, 'PRESTATION APRES LE TITRE', 0, 'L');
					checkPagination($pdf->getPage() === $expectedPages, 'First item moved away from its title: '.$case);
					if ($outputDirectory !== null) {
						$filename = $outputDirectory.'/title-'.$startY.'-'.(int) $background.'-'.(int) $forced.'-'.(int) $html.'-'.(int) $longDescription.'.pdf';
						$pdf->Output($filename, 'F');
					}
					$caseCount++;
				}
			}
		}
	}
}

// A label may itself span pages; its backgrounds must not change the text flow.
$object->context = array(); // Legacy models without configurable columns.
foreach (array(70, 250) as $startY) {
	$referencePosition = array();
	foreach (array(false, true) as $background) {
		$settings = array('SUBTOTAL_TITLE_SIZE' => '14');
		if ($background) {
			$settings['SUBTOTAL_TITLE_BACKGROUNDCOLOR'] = '#d0e8fb';
		}
		$pdf = new TitlePaginationPdf();
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetMargins(10, 42, 10);
		$pdf->SetAutoPageBreak(true, 22);
		$pdf->AddPage();
		$pdf->SetFont('helvetica', '', 9);
		$line->label = str_repeat("Titre long de niveau deux\n", 90);
		$line->qty = 2;
		$line->info_bits = 0;
		$line->desc = '';
		$parameters['posy'] = $startY;
		$actions->pdf_writelinedesc($parameters, $object, $action);
		$position = array($pdf->getPage(), $pdf->GetY());
		if (!$background) {
			$referencePosition = $position;
		} else {
			checkPagination($position === $referencePosition, 'Background changed a multipage label position.');
			checkPagination(count($pdf->backgrounds) === $pdf->getNumPages(), 'A multipage label lost a background segment.');
			foreach ($pdf->backgrounds as $cell) {
				checkPagination($cell['y'] + $cell['height'] <= 275.01, 'Background entered the footer area.');
			}
		}
		$caseCount++;
	}
}

// Respect an explicitly disabled automatic page break and configured offsets.
$settings = array('SUBTOTAL_TITLE_BACKGROUNDCOLOR' => '#d0e8fb', 'SUBTOTAL_TITLE_BACKGROUND_CELL_HEIGHT_OFFSET' => '2', 'SUBTOTAL_TITLE_BACKGROUND_CELL_POS_Y_OFFSET' => '1');
$pdf = new TitlePaginationPdf();
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 42, 10);
$pdf->SetAutoPageBreak(false, 22);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 9);
$line->label = 'Titre avec fond';
$parameters['posy'] = 70;
$actions->pdf_writelinedesc($parameters, $object, $action);
checkPagination(!$pdf->getAutoPageBreak() && $pdf->getBreakMargin() === 22, 'Disabled automatic pagination was not restored.');
checkPagination($pdf->getNumPages() === 1 && abs($pdf->backgrounds[0]['y'] - 71) < 0.01, 'Configured background offset changed.');
checkPagination(abs($pdf->backgrounds[0]['height'] - ($pdf->GetY() - 70 + 2)) < 0.01, 'Configured background height offset changed.');
$caseCount++;
print 'OK: '.$caseCount.' native TCPDF title pagination scenarios on PHP '.PHP_VERSION.".\n";
