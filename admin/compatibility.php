<?php
/* Copyright (C) 2026 ATM Consulting x Les Métiers du Bâtiment <developpeur@lesmetiersdubatiment.fr>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
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
require_once __DIR__.'/../lib/subtotal.lib.php';
require_once __DIR__.'/../class/subtotalcompatibility.class.php';

$langs->loadLangs(array('admin', 'subtotal@subtotal'));
if (empty($user->admin)) {
	accessforbidden();
}

$pageName = 'Compatibility';
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=subtotal">'.$langs->trans('BackToModuleList').'</a>';
llxHeader('', $langs->trans($pageName));
print load_fiche_titre($langs->trans($pageName), $linkback, 'technic');
$head = subtotalAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $langs->trans('Module104777Name'), -1, 'subtotal@subtotal');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('CompatibilityComponent').'</th><th>'.$langs->trans('DetectedVersion').'</th><th>'.$langs->trans('MinimumVersion').'</th><th>'.$langs->trans('Status').'</th></tr>';
$runtimeRows = array(
	array('PHP', PHP_VERSION, SubtotalCompatibility::MIN_PHP_VERSION, SubtotalCompatibility::isPhpVersionAtLeast(SubtotalCompatibility::MIN_PHP_VERSION)),
	array('Dolibarr', DOL_VERSION, SubtotalCompatibility::MIN_DOLIBARR_VERSION, SubtotalCompatibility::isDolibarrVersionAtLeast(SubtotalCompatibility::MIN_DOLIBARR_VERSION)),
);
foreach ($runtimeRows as $row) {
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($row[0]).'</td><td>'.dol_escape_htmltag($row[1]).'</td><td>'.dol_escape_htmltag($row[2]).'</td><td>';
	print $row[3] ? '<span class="badge badge-status4">'.$langs->trans('Available').'</span>' : '<span class="badge badge-status8">'.$langs->trans('Unavailable').'</span>';
	print '</td></tr>';
}
print '</table><br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Feature').'</th><th>'.$langs->trans('Description').'</th><th>'.$langs->trans('MinimumDolibarrVersion').'</th><th>'.$langs->trans('CoreAvailableFrom').'</th><th>'.$langs->trans('ModuleAvailableFrom').'</th><th>'.$langs->trans('MinimumPHPVersion').'</th><th>'.$langs->trans('CompatibilityCheck').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('Reason').'</th></tr>';
foreach (SubtotalCompatibility::getCompatibilityFeatures() as $feature) {
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans($feature['label']).'</td>';
	print '<td>'.$langs->trans($feature['description']).'</td>';
	print '<td>'.dol_escape_htmltag($feature['min_dolibarr']).'</td>';
	print '<td>'.dol_escape_htmltag($feature['core_available_from']).'</td>';
	print '<td>'.dol_escape_htmltag($feature['module_available_from']).'</td>';
	print '<td>'.dol_escape_htmltag($feature['min_php']).'</td>';
	print '<td>'.dol_escape_htmltag($feature['compatibility_check']).'</td>';
	print '<td>'.($feature['available'] ? '<span class="badge badge-status4">'.$langs->trans('Available').'</span>' : '<span class="badge badge-status8">'.$langs->trans('Unavailable').'</span>').'</td>';
	print '<td>'.($feature['available'] ? '' : $langs->trans($feature['reason'])).'</td>';
	print '</tr>';
}
print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();
