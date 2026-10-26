<?php
/**
 * Regression tests for insertion into open and nested Subtotal sections.
 * Run with: php test/line_insertion_test.php
 *
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

class DolibarrTriggers
{
	public $error = '';
	public $errors = array();
}

require_once __DIR__.'/../class/subtotal.class.php';
require_once __DIR__.'/../core/triggers/interface_90_modSubtotal_subtotaltrigger.class.php';

class InsertionTestDocument
{
	public $lines = array();
	public $ranks = array();
	public $error = 'Rank update failed';
	public $errors = array();
	public $failOnId = 0;

	public function __construct(array $rows)
	{
		foreach ($rows as $index => $row) {
			$line = (object) array(
				'id' => $row[0], 'rang' => $index + 1, 'qty' => $row[1],
				'product_type' => $row[2] ? 9 : 0,
				'special_code' => $row[2] ? TSubtotal::$module_number : 0,
			);
			$this->lines[] = $line;
			$this->ranks[$line->id] = $line->rang;
		}
	}

	public function updateRangOfLine($id, $rank)
	{
		if ($id === $this->failOnId) {
			return -1;
		}
		$this->ranks[$id] = $rank;
		return 1;
	}

	public function orderedIds()
	{
		$ranks = $this->ranks;
		asort($ranks);
		return array_keys($ranks);
	}
}

$trigger = (new ReflectionClass('Interfacesubtotaltrigger'))->newInstanceWithoutConstructor();
$failures = array();
$cases = array(
	// Rows: ID, quantity (title/subtotal level), technical Subtotal line.
	'open section before another title' => array('addToEnd', array(array(1, 1, true), array(2, 1, false), array(3, 1, true), array(4, 1, false), array(99, 1, false)), 1, array(1, 2, 99, 3, 4)),
	'closed section' => array('addToEnd', array(array(1, 1, true), array(2, 1, false), array(3, 99, true), array(4, 1, true), array(99, 1, false)), 1, array(1, 2, 99, 3, 4)),
	'nested section without a subtotal' => array('addToEnd', array(array(1, 1, true), array(2, 2, true), array(3, 1, false), array(4, 2, true), array(5, 99, true), array(99, 1, false)), 2, array(1, 2, 3, 99, 4, 5)),
	'ancestor subtotal closes an open child' => array('addToEnd', array(array(1, 1, true), array(2, 2, true), array(3, 1, false), array(4, 99, true), array(99, 1, false)), 2, array(1, 2, 3, 99, 4)),
	'product quantity is not a subtotal' => array('addToEnd', array(array(1, 1, true), array(2, 99, false), array(3, 1, false), array(4, 99, true), array(99, 1, false)), 1, array(1, 2, 3, 99, 4)),
	'parent section includes nested subtotals' => array('addToEnd', array(array(1, 1, true), array(2, 2, true), array(3, 1, false), array(4, 98, true), array(5, 99, true), array(99, 1, false)), 1, array(1, 2, 3, 4, 99, 5)),
	'last open section' => array('addToEnd', array(array(1, 1, true), array(2, 1, false), array(99, 1, false)), 1, array(1, 2, 99)),
	'directly below a title' => array('addToBegin', array(array(1, 1, true), array(2, 1, false), array(3, 99, true), array(99, 1, false)), 1, array(1, 99, 2, 3)),
	'invalid title rank' => array('addToBegin', array(array(1, 1, true), array(2, 1, false), array(3, 1, false), array(99, 1, false)), 2, array(1, 2, 3, 99)),
);

foreach ($cases as $label => $case) {
	$document = new InsertionTestDocument($case[1]);
	$newLine = clone $document->lines[count($document->lines) - 1];
	$trigger->{$case[0]}($document, $newLine, $case[2]);
	if ($document->orderedIds() !== $case[3] || count(array_unique($document->ranks)) !== count($document->ranks)) {
		$failures[] = $label.': '.json_encode($document->ranks);
	}
}

$document = new InsertionTestDocument($cases['closed section'][1]);
$newLine = clone $document->lines[count($document->lines) - 1];
$document->failOnId = 3;
if ($trigger->addToEnd($document, $newLine, 1) !== -1 || $newLine->rang !== 5) {
	$failures[] = 'Failed rank update must propagate to the caller without reporting a successful move';
}
$document->failOnId = 99;
if ($trigger->addToEnd($document, $newLine, 1) !== -1 || $newLine->rang !== 5) {
	$failures[] = 'Failed final line move must propagate to the caller';
}

if ($failures) {
	fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
	exit(1);
}
print "OK: ".(count($cases) + 2)." insertion scenarios (simulated native rank writes).\n";
