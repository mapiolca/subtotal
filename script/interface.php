<?php
/**
 * Protected AJAX controller for Subtotal document-line operations.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require '../config.php';

dol_include_once('/subtotal/lib/subtotal.lib.php');
dol_include_once('/subtotal/class/subtotal.class.php');
dol_include_once('/subtotal/class/subtotalaccess.class.php');
require_once __DIR__.'/../class/subTotalJsonResponse.class.php';

global $db, $langs, $user;

$langs->load('subtotal@subtotal');
header('Content-Type: application/json; charset=UTF-8');

$response = new SubTotalJsonResponse();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	subtotalAjaxError($response, 'SubtotalAjaxPostRequired', 405);
}
if (!isModEnabled('subtotal')) {
	subtotalAjaxError($response, 'ModuleNotEnabled', 503);
}

// main.inc.php validates the CSRF token for POST requests carrying a sensitive action.
$action = GETPOST('action', 'aZ09');
if (empty($action) || empty(GETPOST('token', 'alphanohtml'))) {
	subtotalAjaxError($response, 'ErrorBadToken', 403);
}

$data = GETPOST('data', 'array');
if (!is_array($data)) {
	$data = array();
}

$element = !empty($data['element']) ? SubtotalAccess::normalizeElement($data['element']) : SubtotalAccess::normalizeElement(GETPOST('element', 'aZ09'));
$elementId = !empty($data['element_id']) ? (int) $data['element_id'] : GETPOSTINT('elementid');
$lineId = !empty($data['lineid']) ? (int) $data['lineid'] : GETPOSTINT('lineid');

$object = SubtotalAccess::fetchObject($element, $elementId);
if (!is_object($object)) {
	subtotalAjaxError($response, 'ErrorFetchingElement', 404);
}

if ($action === 'getLinesFromTitle') {
	if (!SubtotalAccess::canRead($user, $object)) {
		subtotalAjaxError($response, 'NotEnoughPermissions', 403);
	}
	$titleLine = SubtotalAccess::findLine($object, $lineId);
	if (!is_object($titleLine) || !TSubtotal::isTitle($titleLine)) {
		subtotalAjaxError($response, 'SubtotalLineNotFound', 404);
	}

	$result = array();
	$subline = TSubtotal::getSubLineOfTitle($object, $titleLine->rang);
	foreach ($object->lines as $line) {
		if ((int) $line->product_type === 9 || (int) $line->rang <= (int) $titleLine->rang) {
			continue;
		}
		if (is_object($subline) && (int) $line->rang >= (int) $subline->rang) {
			continue;
		}
		$parentLine = TSubtotal::getParentTitleOfLine($object, $line->rang);
		if (is_object($parentLine)) {
			$result[(int) $parentLine->id][] = (int) $line->id;
		}
	}
	$response->result = 1;
	$response->data = $result;
	print $response->getJsonResponse();
	exit;
}

if (!SubtotalAccess::canWrite($user, $object)) {
	subtotalAjaxError($response, 'NotEnoughPermissions', 403);
}
if (!SubtotalAccess::isEditable($object)) {
	subtotalAjaxError($response, 'SubtotalObjectNotEditable', 409);
}

switch ($action) {
	case 'updateLineNC':
		if (!is_object(SubtotalAccess::findLine($object, $lineId))) {
			subtotalAjaxError($response, 'SubtotalLineNotFound', 404);
		}
		$result = _updateLineNC($element, $elementId, $lineId, !empty($data['subtotal_nc']) ? 1 : GETPOSTINT('subtotal_nc'));
		if ($result < 0) {
			subtotalAjaxError($response, 'subtotal_update_nc_error', 422);
		}
		$response->result = 1;
		$response->msg = $langs->trans('subtotal_update_nc_success');
		break;

	case 'updateHideBlockData':
		$titleStatusList = isset($data['titleStatusList']) && is_array($data['titleStatusList']) ? $data['titleStatusList'] : array();
		if (empty($titleStatusList)) {
			subtotalAjaxError($response, 'SubtotalAjaxMissingStatusList', 400);
		}

		$db->begin();
		$error = 0;
		foreach ($titleStatusList as $lineStatus) {
			$statusLineId = isset($lineStatus['id']) ? (int) $lineStatus['id'] : 0;
			$status = !empty($lineStatus['status']) ? 1 : 0;
			$line = SubtotalAccess::findLine($object, $statusLineId);
			if (!is_object($line) || !TSubtotal::isTitle($line)) {
				$error++;
				break;
			}
			$line->fetch_optionals();
			$line->array_options['options_hideblock'] = $status;
			if ($line->insertExtraFields() < 0) {
				$error++;
				break;
			}
		}
		if ($error) {
			$db->rollback();
			subtotalAjaxError($response, 'SubtotalAjaxUpdateFailed', 422);
		}
		$db->commit();
		$response->result = 1;
		$response->msg = $langs->trans('RecordSaved');
		break;

	case 'updateAllHideBlockData':
		$value = !empty($data['value']) ? 1 : 0;
		$db->begin();
		$error = 0;
		foreach ($object->lines as $line) {
			if (!TSubtotal::isTitle($line)) {
				continue;
			}
			$line->fetch_optionals();
			$line->array_options['options_hideblock'] = $value;
			if ($line->insertExtraFields() < 0) {
				$error++;
				break;
			}
		}
		if ($error) {
			$db->rollback();
			subtotalAjaxError($response, 'SubtotalAjaxUpdateFailed', 422);
		}
		$db->commit();
		$response->result = 1;
		$response->msg = $langs->trans('RecordSaved');
		break;

	default:
		subtotalAjaxError($response, 'SubtotalAjaxUnknownAction', 400);
}

print $response->getJsonResponse();

/**
 * Return a translated JSON error and stop execution.
 *
 * @param SubTotalJsonResponse $response Response.
 * @param string               $translationKey Translation key.
 * @param int                  $status HTTP status.
 * @return void
 */
function subtotalAjaxError($response, $translationKey, $status)
{
	global $langs;

	http_response_code((int) $status);
	$response->result = 0;
	$response->msg = $langs->trans($translationKey);
	print $response->getJsonResponse();
	exit;
}
