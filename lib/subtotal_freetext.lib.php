<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * @param SubtotalFreeText $entry Entry.
 * @return string Native editor form fragment.
 */
function subtotalFreeTextForm($entry)
{
	global $langs;
	require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
	require_once __DIR__.'/../class/subtotalcompatibility.class.php';
	ob_start();
	print '<form class="subtotal-freetext-form" method="POST">';
	print '<input type="hidden" name="token" value="'.currentToken().'">';
	print '<input type="hidden" name="id" value="'.((int) $entry->id).'">';
	print '<input type="hidden" name="signature" value="'.$entry->fingerprint().'">';
	print '<label class="fieldrequired" for="subtotal_freetext_label">'.$langs->trans('Label').'</label> ';
	print '<input id="subtotal_freetext_label" name="label" class="minwidth200" maxlength="255" required value="'.dol_escape_htmltag($entry->label).'">';
	print ' <span tabindex="0" class="subtotal-freetext-active" role="switch" aria-checked="'.($entry->active ? 'true' : 'false').'" aria-label="'.dol_escape_htmltag($langs->transnoentities('Active')).'">';
	// This switch edits a pending form value; objectonoff.php must never write this dictionary.
	print '<span id="set_subtotal_freetext_form_active" class="linkobject '.($entry->active ? 'hideobject' : '').'">'.img_picto($langs->trans('Disabled'), 'switch_off').'</span>';
	print '<span id="del_subtotal_freetext_form_active" class="linkobject '.($entry->active ? '' : 'hideobject').'">'.img_picto($langs->trans('Enabled'), 'switch_on').'</span></span>';
	print '<input type="hidden" name="active" value="'.((int) $entry->active).'">';
	print '<div class="marginbottomonly margintoponly">';
	$data = $entry->toArray();
	$editor = new DolEditor('subtotal_freetext_content', $data['content'], '', 220, 'dolibarr_details', '', false, -1,
		SubtotalCompatibility::isFeatureAvailable('freetext_html_editor'), 8, '100%');
	$editor->Create();
	print '</div><div class="center"><button class="button" type="submit">'.$langs->trans('Save').'</button> ';
	print '<button class="button button-cancel subtotal-freetext-reset" type="button">'.$langs->trans('Cancel').'</button></div></form>';
	return ob_get_clean();
}

/**
 * @param array{rows:array<int,array{id:int,label:string,content:string,active:int,signature:string}>,total:int} $data Entries.
 * @param array{page:int,limit:int,search:string,sortfield:string,sortorder:string,element:string,elementid:int} $query List parameters.
 * @return string Native list fragment, without a second page header or footer.
 */
function subtotalFreeTextList($data, array $query)
{
	global $langs, $db;
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
	$form = new Form($db);
	$url = dol_buildpath('/subtotal/script/freetext.php', 1);
	$param = '&view=list&element='.urlencode($query['element']).'&elementid='.$query['elementid'].'&search_label='.urlencode($query['search']).'&limit='.$query['limit'];
	ob_start();
	print '<form class="subtotal-freetext-search" method="GET" action="'.$url.'">';
	print '<input type="hidden" name="view" value="list">';
	print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($query['sortfield']).'">';
	print '<input type="hidden" name="sortorder" value="'.$query['sortorder'].'">';
	$newbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', '#', 'subtotal-freetext-new');
	$hasNext = ($query['page'] + 1) * $query['limit'] < $data['total'];
	print_barre_liste($langs->trans('SubtotalFreeTextManage'), $query['page'], $url, $param, $query['sortfield'], $query['sortorder'], '', count($data['rows']) + ($hasNext ? 1 : 0), $data['total'], 'list', 0, $newbutton, '', $query['limit']);
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre_filter"><td><input name="search_label" class="flat maxwidth200" value="'.dol_escape_htmltag($query['search']).'" aria-label="'.dol_escape_htmltag($langs->transnoentities('Label')).'"></td><td></td><td></td>';
	print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td></tr>';
	print '<tr class="liste_titre">';
	print_liste_field_titre('Label', $url, 'label', '', $param, '', $query['sortfield'], $query['sortorder']);
	print '<th>'.$langs->trans('Content').'</th>';
	print_liste_field_titre('Status', $url, 'active', '', $param, '', $query['sortfield'], $query['sortorder'], 'center ');
	print '<th class="center maxwidthsearch">'.$langs->trans('Actions').'</th></tr>';
	foreach ($data['rows'] as $row) {
		print '<tr class="oddeven" data-id="'.$row['id'].'" data-signature="'.$row['signature'].'">';
		print '<td>'.dol_escape_htmltag($row['label']).'</td><td>'.dol_escape_htmltag(dol_trunc(dol_string_nohtmltag($row['content']), 160)).'</td>';
		print '<td class="nowraponall center"><span tabindex="0" class="subtotal-freetext-toggle" role="switch" aria-checked="'.($row['active'] ? 'true' : 'false').'" aria-label="'.dol_escape_htmltag($langs->transnoentities('Active')).'">';
		print img_picto($langs->trans($row['active'] ? 'Enabled' : 'Disabled'), $row['active'] ? 'switch_on' : 'switch_off').'</span> ';
		print dolGetStatus($langs->trans($row['active'] ? 'Enabled' : 'Disabled'), '', '', $row['active'] ? 'status4' : 'status8', 5).'</td>';
		print '<td class="center maxwidthsearch nowraponall"><a href="#" class="reposition marginleftonly paddingleft marginrightonly paddingright editfielda subtotal-freetext-edit" title="'.dol_escape_htmltag($langs->transnoentities('Modify')).'">'.img_edit().'</a>';
		print '<a href="#" class="reposition marginleftonly paddingleft marginrightonly paddingright subtotal-freetext-delete" title="'.dol_escape_htmltag($langs->transnoentities('Delete')).'">'.img_delete().'</a></td></tr>';
	}
	if (!$data['rows']) print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	print '</table></div></form>';
	return ob_get_clean();
}
