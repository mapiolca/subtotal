<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
// Keep the card's token stable across nested Ajax dialogs; enforce native POST CSRF checks.
define('NOTOKENRENEWAL', 1);
define('CSRFCHECK_WITH_TOKEN', 1);
define('NOREQUIREMENU', 1);
$res = 0;
if (!$res && is_file(__DIR__.'/../../main.inc.php')) $res = include __DIR__.'/../../main.inc.php';
if (!$res && is_file(__DIR__.'/../../../main.inc.php')) $res = include __DIR__.'/../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/../class/subtotalfreetext.class.php';
require_once __DIR__.'/../class/subtotalaccess.class.php';
require_once __DIR__.'/../class/subTotalJsonResponse.class.php';
require_once __DIR__.'/../lib/subtotal_freetext.lib.php';
$langs->loadLangs(array('main', 'errors', 'admin', 'subtotal@subtotal'));
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$response = new SubTotalJsonResponse();
$response->newToken = currentToken(); // Ajax keeps the current token, including with per-page token renewal enabled.

if (!isModEnabled('subtotal')) subtotalFreeTextError('ModuleNotEnabled', 503);
if (!getDolGlobalInt('SUBTOTAL_ALLOW_ADD_BLOCK')) subtotalFreeTextError('NotEnoughPermissions', 403);
$action = GETPOST('action', 'aZ09');
$view = GETPOST('view', 'aZ09');
if (!in_array($action, array('', 'create', 'update', 'toggle', 'delete', 'insert'), true)
	|| !in_array($view, array('', 'active', 'manager', 'list', 'edit'), true)) subtotalFreeTextError('SubtotalAjaxUnknownAction', 400);
if ($action !== '' && ($_SERVER['REQUEST_METHOD'] !== 'POST' || GETPOST('token', 'alphanohtml') === '')) subtotalFreeTextError('ErrorBadToken', 403);
// main.inc.php clears POST actions after a wrong token: never treat that as a successful read.
if ($action === '' && $_SERVER['REQUEST_METHOD'] === 'POST') subtotalFreeTextError('ErrorBadToken', 403);
if ($action === '' && $_SERVER['REQUEST_METHOD'] !== 'GET') subtotalFreeTextError('SubtotalAjaxUnknownAction', 405);

$element = SubtotalAccess::normalizeElement(GETPOST('element', 'aZ09'));
$elementid = GETPOSTINT('elementid');
$object = SubtotalAccess::fetchObject($element, $elementid);
if (!is_object($object) || empty($object->entity) || !SubtotalAccess::isObjectEntityAllowed($object)) subtotalFreeTextError('ErrorRecordNotFound', 404);

// Functional rights are direct native calls. The native parent scope remains an independent check.
$canWrite = false;
switch ($element) {
	case 'propal':
		$canWrite = $user->hasRight('propal', 'lire') && $user->hasRight('propal', 'creer');
		if ($canWrite) restrictedArea($user, 'propal', $object->id);
		break;
	case 'commande':
		$canWrite = $user->hasRight('commande', 'lire') && $user->hasRight('commande', 'creer');
		if ($canWrite) restrictedArea($user, 'commande', $object->id);
		break;
	case 'facture':
	case 'facturerec':
		$canWrite = $user->hasRight('facture', 'lire') && $user->hasRight('facture', 'creer');
		if ($canWrite) restrictedArea($user, 'facture', $object->id, $element === 'facturerec' ? 'facture_rec' : '', '', 'fk_soc', 'rowid', 1);
		break;
	case 'supplier_proposal':
		$canWrite = $user->hasRight('supplier_proposal', 'lire') && $user->hasRight('supplier_proposal', 'creer');
		if ($canWrite) restrictedArea($user, 'supplier_proposal', $object->id);
		break;
	case 'order_supplier':
		$canWrite = $user->hasRight('fournisseur', 'commande', 'lire') && $user->hasRight('fournisseur', 'commande', 'creer');
		if ($canWrite) restrictedArea($user, 'fournisseur', $object, 'commande_fournisseur', 'commande', 'fk_soc', 'rowid', 1);
		break;
	case 'invoice_supplier':
		$canWrite = $user->hasRight('fournisseur', 'facture', 'lire') && $user->hasRight('fournisseur', 'facture', 'creer');
		if ($canWrite) restrictedArea($user, 'fournisseur', $object->id, 'facture_fourn', 'facture', 'fk_soc', 'rowid', 1);
		break;
}
if (!$canWrite || !SubtotalAccess::isEditable($object)) subtotalFreeTextError('NotEnoughPermissions', 403);
$canManage = empty($user->socid) && $user->hasRight('subtotal', 'freetext', 'manage');
if (($action !== '' && $action !== 'insert') || in_array($view, array('manager', 'list', 'edit'), true)) {
	if (!$canManage) subtotalFreeTextError('NotEnoughPermissions', 403);
}

$entry = new SubtotalFreeText($db, (int) $conf->entity);
if ($action !== '') {
	// Session-scoped replay protection lives at the web boundary, never in the model.
	$requestId = GETPOST('request_id', 'aZ09');
	if ($requestId === '' || dol_strlen($requestId) > 80) subtotalFreeTextError('SubtotalFreeTextSaveFailed', 400);
	$replayKey = hash('sha256', $user->id.':'.$conf->entity.':'.$element.':'.$elementid.':'.$action.':'.$requestId);
	$inputHash = hash('sha256', json_encode($_POST));
	if (isset($_SESSION['subtotal_freetext_replays'][$replayKey])) {
		$saved = $_SESSION['subtotal_freetext_replays'][$replayKey];
		if ($saved['input'] !== $inputHash) subtotalFreeTextError('SubtotalFreeTextConflict', 409);
		$response->result = 1;
		$response->data = $saved['data'];
		print $response->getJsonResponse();
		exit;
	}
	if ($action === 'insert') {
		require_once __DIR__.'/../class/subtotal.class.php';
		$content = SubtotalFreeText::sanitizeContent(GETPOST('content', 'restricthtml'));
		if (trim($content) === '') subtotalFreeTextError('SubtotalFreeTextContentRequired', 422);
		$titleRank = GETPOSTINT('under_title');
		if ($titleRank > 0) {
			$found = false;
			foreach ($object->lines as $line) {
				if ((int) $line->rang === $titleRank && TSubtotal::isTitle($line)) $found = true;
			}
			if (!$found) subtotalFreeTextError('SubtotalLineNotFound', 422);
		}
		$rank = GETPOSTINT('rank');
		$id = TSubtotal::addSubTotalLine($object, $content, 50, $rank > 0 ? $rank : -1);
		if ($id <= 0) subtotalFreeTextError('SubtotalFreeTextSaveFailed', 422);
		$response->data = array('id' => (int) $id);
	} else {
		$entry->id = GETPOSTINT('id');
		$values = array('label' => GETPOST('label', 'alphanohtml'), 'content' => GETPOST('content', 'restricthtml'), 'active' => GETPOSTINT('active') === 0 ? 0 : 1);
		$result = $entry->mutate($action, $user, $values, GETPOST('signature', 'aZ09'));
		if ($result === -2) subtotalFreeTextError('SubtotalFreeTextConflict', 409);
		if ($result <= 0) {
			$fieldError = $entry->getFieldError('label');
			http_response_code($fieldError !== '' ? 422 : 500);
			$response->msg = is_string($fieldError) && $fieldError !== '' ? html_entity_decode($fieldError, ENT_QUOTES, 'UTF-8') : $langs->transnoentities('SubtotalFreeTextSaveFailed');
			$response->data = array('fieldErrors' => $fieldError !== '' ? array('label' => $fieldError) : array());
			print $response->getJsonResponse();
			exit;
		}
		$response->data = array('entry' => $entry->toArray());
	}
	if (!isset($_SESSION['subtotal_freetext_replays']) || count($_SESSION['subtotal_freetext_replays']) >= 50) $_SESSION['subtotal_freetext_replays'] = array();
	$_SESSION['subtotal_freetext_replays'][$replayKey] = array('input' => $inputHash, 'data' => $response->data);
} elseif ($view === 'edit') {
	$id = GETPOSTINT('id');
	if ($id > 0 && $entry->fetch($id) <= 0) subtotalFreeTextError('ErrorRecordNotFound', 404);
	$response->data = array('form' => subtotalFreeTextForm($entry));
} else {
	$activeOnly = $view === '' || $view === 'active';
	$query = array('page' => max(0, GETPOSTINT('page')), 'limit' => min(100, max(1, GETPOSTINT('limit') ?: 20)),
		'search' => GETPOST('search_label', 'alphanohtml'), 'sortfield' => GETPOST('sortfield', 'aZ09') === 'active' ? 'active' : 'label',
		'sortorder' => GETPOST('sortorder', 'aZ09') === 'DESC' ? 'DESC' : 'ASC', 'element' => $element, 'elementid' => $elementid);
	$data = $entry->listEntries($activeOnly ? '' : $query['search'], $activeOnly, $activeOnly ? 0 : $query['limit'], $query['page'] * $query['limit'], $query['sortfield'], $query['sortorder']);
	if ($data === false) subtotalFreeTextError('SubtotalFreeTextLoadFailed', 500);
	$response->data = $activeOnly ? $data : array('list' => subtotalFreeTextList($data, $query));
	if ($view === 'manager') $response->data['form'] = subtotalFreeTextForm($entry);
}
$response->result = 1;
print $response->getJsonResponse();

/**
 * @param string $key Translation.
 * @param int $status HTTP status.
 * @return void
 */
function subtotalFreeTextError($key, $status)
{
	global $response, $langs;
	http_response_code($status);
	$response->msg = $langs->transnoentities($key);
	print $response->getJsonResponse();
	exit;
}
