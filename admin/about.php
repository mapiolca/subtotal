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

$moduleDescriptor = new modSubtotal($db);
$title = $langs->trans('SubtotalAbout');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=subtotal">'.$langs->trans('BackToModuleList').'</a>';

llxHeader('', $title);

print load_fiche_titre($title, $linkback, 'info');
$head = subtotalAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $title, -1, 'modsubtotal@subtotal');

print '<div class="underbanner opacitymedium">'.$langs->trans('SubtotalAboutPage').'</div>';
print '<br>';

print '<div class="fichecenter">';

print '<div class="fichehalfleft">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('SubtotalAboutGeneral').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('SubtotalAboutVersion').'</td><td>'.dol_escape_htmltag((string) $moduleDescriptor->version).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('SubtotalAboutFamily').'</td><td>'.dol_escape_htmltag((string) $moduleDescriptor->family).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('SubtotalAboutDescription').'</td><td>'.dol_escape_htmltag($langs->trans($moduleDescriptor->description)).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('SubtotalAboutMaintainer').'</td><td>'.dol_escape_htmltag((string) $moduleDescriptor->editor_name).'</td></tr>';
print '</table>';
print '</div>';
print '</div>';

print '<div class="fichehalfright">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('SubtotalAboutResources').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('SubtotalAboutDocumentation').'</td><td><a href="'.dol_buildpath('/subtotal/README.md', 1).'" target="_blank" rel="noopener">'.$langs->trans('SubtotalAboutDocumentationLink').'</a></td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('SubtotalAboutSupport').'</td><td>'.dol_escape_htmltag($langs->trans('SubtotalAboutSupportValue')).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('SubtotalAboutContact').'</td><td><a href="'.dol_escape_htmltag((string) $moduleDescriptor->editor_url).'" target="_blank" rel="noopener">'.dol_escape_htmltag((string) $moduleDescriptor->editor_url).'</a></td></tr>';
print '</table>';
print '</div>';
print '</div>';

print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
