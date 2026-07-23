<?php
/* Copyright (C) 2026 ATM Consulting x Les Métiers du Bâtiment <developpeur@lesmetiersdubatiment.fr>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * \file admin/setup.php
 * \brief Subtotal settings.
 */

$res = 0;
if (!$res && file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once __DIR__.'/../lib/subtotal.lib.php';

$langs->loadLangs(array('admin', 'subtotal@subtotal', 'propal', 'orders', 'bills', 'supplier', 'supplier_proposal'));

if (empty($user->admin)) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/**
 * @var array<string, array{type:string, section:string, options?:array<string, string>, default?:string, help?:string}>
 */
$settings = array(
	'SUBTOTAL_USE_NEW_FORMAT' => array('type' => 'bool', 'section' => 'SubtotalSectionGeneral', 'help' => 'SUBTOTAL_USE_NEW_FORMAT_HELP'),
	'CONCAT_TITLE_LABEL_IN_SUBTOTAL_LABEL' => array('type' => 'bool', 'section' => 'SubtotalSectionGeneral'),
	'SUBTOTAL_USE_NUMEROTATION' => array('type' => 'bool', 'section' => 'SubtotalSectionGeneral'),
	'SUBTOTAL_ALLOW_ADD_BLOCK' => array('type' => 'bool', 'section' => 'SubtotalSectionGeneral'),
	'SUBTOTAL_ALLOW_EDIT_BLOCK' => array('type' => 'bool', 'section' => 'SubtotalSectionGeneral'),
	'SUBTOTAL_ALLOW_REMOVE_BLOCK' => array('type' => 'bool', 'section' => 'SubtotalSectionGeneral'),
	'SUBTOTAL_ALLOW_DUPLICATE_BLOCK' => array('type' => 'bool', 'section' => 'SubtotalSectionGeneral'),
	'SUBTOTAL_ALLOW_DUPLICATE_LINE' => array('type' => 'bool', 'section' => 'SubtotalSectionGeneral'),
	'SUBTOTAL_ALLOW_ADD_LINE_UNDER_TITLE' => array('type' => 'bool', 'section' => 'SubtotalSectionGeneral'),
	'SUBTOTAL_ADD_LINE_UNDER_TITLE_AT_END_BLOCK' => array('type' => 'bool', 'section' => 'SubtotalSectionGeneral'),
	'SUBTOTAL_HIDE_FOLDERS_BY_DEFAULT' => array('type' => 'bool', 'section' => 'SubtotalSectionGeneral'),
	'SUBTOTAL_AUTO_ADD_SUBTOTAL_ON_ADDING_NEW_TITLE' => array('type' => 'bool', 'section' => 'SubtotalSectionGeneral'),
	'SUBTOTAL_HIDE_OPTIONS_TITLE' => array('type' => 'bool', 'section' => 'SubtotalSectionDisplay'),
	'SUBTOTAL_HIDE_OPTIONS_BREAK_PAGE_BEFORE' => array('type' => 'bool', 'section' => 'SubtotalSectionDisplay'),
	'SUBTOTAL_HIDE_OPTIONS_BUILD_DOC' => array('type' => 'bool', 'section' => 'SubtotalSectionDisplay'),
	'DISPLAY_MARGIN_ON_SUBTOTALS' => array('type' => 'bool', 'section' => 'SubtotalSectionDisplay'),
	'SUBTOTAL_DISABLE_SUMMARY' => array('type' => 'bool', 'section' => 'SubtotalSectionDisplay'),
	'SUBTOTAL_BLOC_FOLD_MODE' => array(
		'type' => 'select',
		'section' => 'SubtotalSectionDisplay',
		'default' => 'default',
		'options' => array(
			'default' => $langs->trans('HideSubtitleOnFold'),
			'keepTitle' => $langs->trans('KeepSubtitleDisplayOnFold'),
		),
	),
	'SUBTOTAL_TEXT_FOR_TITLE_ORDETSTOINVOICE' => array('type' => 'string', 'section' => 'SubtotalSectionDisplay', 'help' => 'SUBTOTAL_TEXT_FOR_TITLE_ORDETSTOINVOICE_info'),
	'SUBTOTAL_TITLE_STYLE' => array('type' => 'style', 'section' => 'SubtotalSectionDisplay', 'default' => 'BU'),
	'SUBTOTAL_TEXT_LINE_STYLE' => array('type' => 'style', 'section' => 'SubtotalSectionDisplay'),
	'SUBTOTAL_TITLE_SIZE' => array('type' => 'integer', 'section' => 'SubtotalSectionDisplay', 'default' => '9', 'help' => 'SUBTOTAL_TITLE_SIZE_info'),
	'SUBTOTAL_SUBTOTAL_STYLE' => array('type' => 'style', 'section' => 'SubtotalSectionDisplay', 'default' => 'B'),
	'SUBTOTAL_TITLE_BACKGROUNDCOLOR' => array('type' => 'color', 'section' => 'SubtotalSectionDisplay', 'default' => '#ffffff'),
	'SUBTOTAL_SUBTOTAL_BACKGROUNDCOLOR' => array('type' => 'color', 'section' => 'SubtotalSectionDisplay', 'default' => '#ebebeb'),
	'SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS' => array('type' => 'bool', 'section' => 'ManageNonCompris'),
	'SUBTOTAL_TFIELD_TO_KEEP_WITH_NC' => array(
		'type' => 'multiselect',
		'section' => 'ManageNonCompris',
		'options' => array(
			'pdf_getlineqty' => $langs->trans('Qty'),
			'pdf_getlinevatrate' => $langs->trans('VAT'),
			'pdf_getlineupexcltax' => $langs->trans('PriceUHT'),
			'pdf_getlinetotalexcltax' => $langs->trans('TotalHT'),
			'pdf_getlinetotalincltax' => $langs->trans('TotalTTC'),
			'pdf_getlineunit' => $langs->trans('Unit'),
			'pdf_getlineremisepercent' => $langs->trans('Discount'),
		),
	),
	'SUBTOTAL_NONCOMPRIS_UPDATE_PA_HT' => array('type' => 'bool', 'section' => 'ManageNonCompris', 'help' => 'SUBTOTAL_NONCOMPRIS_UPDATE_PA_HT_info'),
	'SUBTOTAL_ALLOW_EXTRAFIELDS_ON_TITLE' => array('type' => 'bool', 'section' => 'SetupForExtrafields'),
	'SUBTOTAL_LIST_OF_EXTRAFIELDS_PROPALDET' => array('type' => 'extrafields', 'section' => 'SetupForExtrafields', 'default' => 'propaldet'),
	'SUBTOTAL_LIST_OF_EXTRAFIELDS_COMMANDEDET' => array('type' => 'extrafields', 'section' => 'SetupForExtrafields', 'default' => 'commandedet'),
	'SUBTOTAL_LIST_OF_EXTRAFIELDS_FACTUREDET' => array('type' => 'extrafields', 'section' => 'SetupForExtrafields', 'default' => 'facturedet'),
	'SUBTOTAL_DEFAULT_DISPLAY_QTY_FOR_SUBTOTAL_ON_ELEMENTS' => array(
		'type' => 'multiselect',
		'section' => 'SubtotalSectionDocuments',
		'help' => 'SUBTOTAL_DEFAULT_DISPLAY_QTY_FOR_SUBTOTAL_ON_ELEMENTS_info',
		'options' => array(
			'propal' => $langs->trans('Proposal'),
			'commande' => $langs->trans('Order'),
			'facture' => $langs->trans('Invoice'),
			'supplier_proposal' => $langs->trans('SupplierProposal'),
			'order_supplier' => $langs->trans('SupplierOrder'),
			'invoice_supplier' => $langs->trans('SupplierInvoice'),
		),
	),
	'NO_TITLE_SHOW_ON_EXPED_GENERATION' => array('type' => 'bool', 'section' => 'SubtotalSectionDocuments'),
	'SUBTOTAL_KEEP_RECAP_FILE' => array('type' => 'bool', 'section' => 'RecapGeneration'),
	'SUBTOTAL_PROPAL_ADD_RECAP' => array('type' => 'bool', 'section' => 'RecapGeneration'),
	'SUBTOTAL_COMMANDE_ADD_RECAP' => array('type' => 'bool', 'section' => 'RecapGeneration'),
	'SUBTOTAL_INVOICE_ADD_RECAP' => array('type' => 'bool', 'section' => 'RecapGeneration'),
	'SUBTOTAL_HIDE_PRICE_DEFAULT_CHECKED' => array('type' => 'bool', 'section' => 'SetupForSubBlocs'),
	'SUBTOTAL_IF_HIDE_PRICES_SHOW_QTY' => array('type' => 'bool', 'section' => 'SetupForSubBlocs'),
	'SUBTOTAL_HIDE_DOCUMENT_TOTAL' => array('type' => 'bool', 'section' => 'SetupForSubBlocs'),
	'SUBTOTAL_SHIPPABLE_ORDER' => array('type' => 'bool', 'section' => 'SetupForSubBlocs'),
	'SUBTOTAL_SHOW_QTY_ON_TITLES' => array('type' => 'bool', 'section' => 'SetupForSubBlocs'),
	'SUBTOTAL_ONLY_HIDE_SUBPRODUCTS_PRICES' => array('type' => 'bool', 'section' => 'SetupForSubBlocs'),
	'SUBTOTAL_ONE_LINE_IF_HIDE_INNERLINES' => array('type' => 'bool', 'section' => 'SubtotalExperimentalZone'),
	'SUBTOTAL_REPLACE_WITH_VAT_IF_HIDE_INNERLINES' => array('type' => 'bool', 'section' => 'SubtotalExperimentalZone'),
);

if ($action === 'save') {
	$error = 0;
	foreach ($settings as $key => $definition) {
		$type = $definition['type'];
		if ($type === 'bool') {
			continue;
		}
		$value = '';
		if ($type === 'multiselect' || $type === 'extrafields') {
			$postValue = GETPOST($key, 'array');
			$value = is_array($postValue) ? implode(',', array_map('dol_sanitizeFileName', $postValue)) : '';
		} elseif ($type === 'bool') {
			$value = GETPOSTINT($key) ? '1' : '0';
		} elseif ($type === 'integer') {
			$value = (string) GETPOSTINT($key);
		} elseif ($type === 'style') {
			$value = strtoupper(GETPOST($key, 'alpha'));
			$value = preg_replace('/[^BIU]/', '', $value);
		} elseif ($type === 'color') {
			$value = GETPOST($key, 'none');
			if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
				$value = isset($definition['default']) ? $definition['default'] : '#ffffff';
			}
		} else {
			$value = GETPOST($key, 'restricthtml');
		}

		$result = dolibarr_set_const($db, $key, $value, 'chaine', 0, '', (int) $conf->entity);
		if ($result <= 0) {
			$error++;
		}
	}

	if ($error) {
		setEventMessages($langs->trans('Error'), null, 'errors');
	} else {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
}

$extrafields = new ExtraFields($db);
foreach ($settings as $key => &$definition) {
	if ($definition['type'] === 'extrafields') {
		$labels = $extrafields->fetch_name_optionals_label($definition['default']);
		$definition['options'] = is_array($labels) ? $labels : array();
	}
}
unset($definition);

$form = new Form($db);
$pageName = 'SubtotalSetup';
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=subtotal">'.$langs->trans('BackToModuleList').'</a>';

llxHeader('', $langs->trans($pageName));
print load_fiche_titre($langs->trans($pageName), $linkback, 'title_setup');

$head = subtotalAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($pageName), -1, 'subtotal@subtotal');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

$currentSection = '';
foreach ($settings as $key => $definition) {
	if ($definition['section'] !== $currentSection) {
		if ($currentSection !== '') {
			print '</table><br>';
		}
		$currentSection = $definition['section'];
		print load_fiche_titre($langs->trans($currentSection), '', '');
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><th>'.$langs->trans('Parameter').'</th><th class="right">'.$langs->trans('Value').'</th></tr>';
	}

	$default = isset($definition['default']) ? $definition['default'] : '';
	$value = getDolGlobalString($key, $default);
	$settingLabel = $langs->trans($key);
	if (!empty($definition['help'])) {
		$settingLabel = $form->textwithpicto($settingLabel, $langs->transnoentities($definition['help']));
	}
	print '<tr class="oddeven"><td><label for="'.$key.'">'.$settingLabel.'</label></td><td class="right">';

	if ($definition['type'] === 'bool') {
		print ajax_constantonoff($key);
	} elseif (in_array($definition['type'], array('select', 'multiselect', 'extrafields'), true)) {
		$selectedValues = explode(',', $value);
		$isMultiple = $definition['type'] !== 'select';
		print '<select class="flat minwidth300" id="'.$key.'" name="'.$key.($isMultiple ? '[]' : '').'"'.($isMultiple ? ' multiple' : '').'>';
		foreach ($definition['options'] as $optionKey => $optionLabel) {
			$selected = in_array((string) $optionKey, $selectedValues, true) ? ' selected' : '';
			print '<option value="'.dol_escape_htmltag((string) $optionKey).'"'.$selected.'>'.dol_escape_htmltag((string) $optionLabel).'</option>';
		}
		print '</select>';
		print ajax_combobox($key);
	} elseif ($definition['type'] === 'color') {
		print '<input class="flat" type="color" id="'.$key.'" name="'.$key.'" value="'.dol_escape_htmltag($value).'">';
	} elseif ($definition['type'] === 'integer') {
		print '<input class="flat width75" type="number" min="1" max="24" id="'.$key.'" name="'.$key.'" value="'.((int) $value).'">';
	} else {
		print '<input class="flat minwidth300" type="text" id="'.$key.'" name="'.$key.'" value="'.dol_escape_htmltag($value).'">';
	}
	print '</td></tr>';
}
if ($currentSection !== '') {
	print '</table>';
}

print '<div class="center"><input class="button button-save" type="submit" value="'.$langs->trans('Save').'"></div>';
print '</form>';
print dol_get_fiche_end();

llxFooter();
$db->close();
