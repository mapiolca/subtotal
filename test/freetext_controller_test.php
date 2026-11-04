<?php
/**
 * Execute actual controller branches with native GETPOST/CommonObject and simulated login/parent/scope.
 * The main.inc.php bootstrap is excluded: this does NOT test native login or CSRF-token validation.
 * Run: php test/freetext_controller_test.php /path/to/dolibarr/htdocs
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
if (PHP_SAPI !== 'cli') exit(1);
$case = isset($argv[2]) ? $argv[2] : '';
if ($case === '') {
	$cases = array('create', 'without-right', 'admin-without-right', 'admin-with-right', 'external', 'without-document-right',
		'outside-business-scope', 'validated', 'other-entity-document', 'shared-document', 'entity-two', 'existing-without-right',
		'without-token', 'csrf-cleared-post', 'get-mutation', 'conflict', 'server-error', 'deleted', 'replay', 'module-off',
		'insert-propal', 'insert-commande', 'insert-facture', 'insert-facturerec', 'insert-supplier_proposal', 'insert-order_supplier', 'insert-invoice_supplier', 'insert-empty');
	foreach ($cases as $scenario) {
		$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($argv[1]).' '.escapeshellarg($scenario);
		passthru($command, $exit);
		if ($exit !== 0) throw new RuntimeException('Failed controller scenario: '.$scenario);
	}
	print 'OK: '.count($cases)." controller branches; session, parent lookup and business scope are simulated.\n";
	exit;
}
$argv[2] = '--fixtures';
require __DIR__.'/freetext_model_test.php';
define('DOL_URL_ROOT', '');
$_SESSION = array('token' => 'fixturetoken', 'newtoken' => 'futurepagetoken');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['PHP_SELF'] = '/subtotal/script/freetext.php';
$_GET = array();
$_POST = array('element' => 'propal', 'elementid' => 1, 'action' => 'create', 'label' => 'New entry', 'content' => '<p>Final <strong>HTML</strong></p>', 'active' => 1, 'token' => 'fixturetoken', 'request_id' => 'request1');
$conf->subtotal = (object) array('enabled' => 1);
$conf->modules['subtotal'] = 1;
$conf->global->SUBTOTAL_ALLOW_ADD_BLOCK = 1;
$conf->cache = array('nonce' => 'fixture');
class FreeTextControllerParent {
	public $element = 'propal'; public $table_element = 'propal'; public $id = 1; public $entity = 1; public $status = 0; public $socid = 1;
	public $special_code; public $inserted = ''; public $lines = array();
	public function addline($content, ...$args) { $this->inserted = $content; return 99; }
}
$fixtureParent = new FreeTextControllerParent();
$db = new FreeTextTestDB();
$saved = new SubtotalFreeText($db, 1);
$saved->mutate('create', $user, array('label' => 'Entity one', 'content' => '<p>One</p>'));
$foreign = new SubtotalFreeText($db, 2);
$foreign->mutate('create', $user, array('label' => 'Entity two', 'content' => '<p>Two</p>'));
$expectedStatus = 200;
$expectedResult = 1;
$scope = true;
switch ($case) {
	case 'without-right': $user->manage = false; $expectedStatus = 403; break;
	case 'admin-without-right': $user->admin = 1; $user->manage = false; $expectedStatus = 403; break;
	case 'admin-with-right': $user->admin = 1; break;
	case 'external': $user->socid = 1; $expectedStatus = 403; break;
	case 'without-document-right': $user->document = false; $expectedStatus = 403; break;
	case 'outside-business-scope': $scope = false; $expectedStatus = 403; break;
	case 'validated': $fixtureParent->status = 1; $expectedStatus = 403; break;
	case 'other-entity-document': $fixtureParent->entity = 2; $expectedStatus = 404; break;
	case 'shared-document': $fixtureParent->entity = 2; break;
	case 'entity-two': $conf->entity = 2; $fixtureParent->entity = 2; break;
	case 'existing-without-right':
		$user->manage = false;
		$_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = array('element' => 'propal', 'elementid' => 1, 'view' => 'active'); $_POST = array();
		break;
	case 'without-token': unset($_POST['token']); $expectedStatus = 403; break;
	case 'csrf-cleared-post': $_POST = array(); $expectedStatus = 403; break;
	case 'get-mutation': $_GET = $_POST; $_POST = array(); $_SERVER['REQUEST_METHOD'] = 'GET'; $expectedStatus = 403; break;
	case 'conflict': $_POST['action'] = 'update'; $_POST['id'] = $saved->id; $_POST['signature'] = str_repeat('a', 64); $expectedStatus = 409; break;
	case 'server-error': $db->fail = 'query'; $expectedStatus = 500; break;
	case 'deleted': $_POST['action'] = 'delete'; $_POST['id'] = 999; $_POST['signature'] = str_repeat('a', 64); $expectedStatus = 409; break;
	case 'replay':
		$key = hash('sha256', '1:1:propal:1:create:request1');
		$_SESSION['subtotal_freetext_replays'][$key] = array('input' => hash('sha256', json_encode($_POST)), 'data' => array('entry' => array('id' => 123)));
		break;
	case 'module-off': $conf->subtotal->enabled = 0; $conf->modules['subtotal'] = 0; $expectedStatus = 503; break;
}
if (strpos($case, 'insert-') === 0) {
	$element = substr($case, 7);
	$_POST['element'] = $fixtureParent->element = $fixtureParent->table_element = $element === 'empty' ? 'propal' : $element;
	$_POST['action'] = 'insert';
	$_POST['under_title'] = 1;
	$user->manage = false;
	$conf->global->MAIN_DISABLE_PDF_AUTOUPDATE = 1;
	$fixtureParent->lines[] = (object) array('id' => 1, 'rang' => 1, 'qty' => 1, 'product_type' => 9, 'special_code' => 104777);
	if ($element === 'empty') { $_POST['content'] = ''; $expectedStatus = 422; }
}
if ($expectedStatus !== 200) $expectedResult = 0;
$initialRows = $db->rows;
function restrictedArea($user, ...$args) { global $scope; if (!$scope) subtotalFreeTextError('NotEnoughPermissions', 403); return 1; }
class FreeTextControllerMC { public function getEntity(...$args) { return '1,2'; } }
if ($case === 'shared-document') $mc = new FreeTextControllerMC();
// Only the two unavailable environment boundaries are replaced. Permission/mutation branches are unchanged.
$source = file_get_contents(__DIR__.'/../script/freetext.php');
$source = substr($source, strpos($source, "require_once __DIR__.'/../class/subtotalfreetext.class.php'"));
$source = str_replace('__DIR__', var_export(realpath(__DIR__.'/../script'), true), $source);
$source = str_replace('SubtotalAccess::fetchObject($element, $elementid)', '$fixtureParent', $source, $replaced);
if ($replaced !== 1) throw new RuntimeException('Parent fixture boundary changed');
ob_start();
register_shutdown_function(function () use ($case, $expectedStatus, $expectedResult, $initialRows) {
	global $db, $conf;
	$output = ob_get_clean();
	$data = json_decode($output, true);
	$status = http_response_code() ?: 200;
	if (!is_array($data) || $status !== $expectedStatus || $data['result'] !== $expectedResult || $data['newToken'] !== 'fixturetoken') {
		fwrite(STDERR, $case.': status '.$status.' response '.$output."\n"); exit(1);
	}
	if (($expectedResult === 0 || $case === 'replay') && $db->rows !== $initialRows) { fwrite(STDERR, "Refused/replayed operation mutated data\n"); exit(1); }
	if ($case === 'existing-without-right' && (count($data['data']['rows']) !== 1 || $data['data']['rows'][0]['label'] !== 'Entity one')) exit(1);
	if (in_array($case, array('create', 'admin-with-right', 'shared-document', 'entity-two'), true)) {
		if ($db->rows[3]['entity'] !== $conf->entity || $db->rows[3]['content'] !== '<p>Final <strong>HTML</strong></p>') exit(1);
	}
	if (strpos($case, 'insert-') === 0 && $case !== 'insert-empty') {
		global $fixtureParent;
		if ($fixtureParent->inserted !== '<p>Final <strong>HTML</strong></p>' || $data['data']['id'] !== 99) exit(1);
	}
});
eval($source);
