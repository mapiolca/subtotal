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
require_once __DIR__.'/../core/modules/modSubtotal.class.php';

$langs->loadLangs(array('admin', 'subtotal@subtotal'));
if (empty($user->admin)) {
	accessforbidden();
}

$descriptor = new modSubtotal($db);
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=subtotal">'.$langs->trans('BackToModuleList').'</a>';
llxHeader('', $langs->trans('subtotalAbout'));
print load_fiche_titre($langs->trans('subtotalAbout'), $linkback, 'subtotal@subtotal');
$head = subtotalAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans('Module104777Name'), -1, 'subtotal@subtotal');

print '<table class="noborder centpercent">';
$aboutRows = array(
	'Module' => $langs->trans('Module104777Name'),
	'Version' => $descriptor->version,
	'Publisher' => $descriptor->editor_name,
	'Description' => $langs->trans('Module104777Desc'),
	'Compatibility' => $langs->trans('SubtotalCompatibilitySummary', '16', '7.0'),
	'Dependencies' => $langs->trans('SubtotalNoRequiredDependency'),
	'MainFeatures' => $langs->trans('SubtotalMainFeatures'),
	'License' => 'GNU GPL v3+',
);
foreach ($aboutRows as $label => $value) {
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans($label).'</td><td>'.dol_escape_htmltag((string) $value).'</td></tr>';
}
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('UsefulLinks').'</td><td>';
print '<a href="https://github.com/mapiolca/subtotal" target="_blank" rel="noopener noreferrer">'.$langs->trans('Repository').'</a>';
print '</td></tr>';
print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();
