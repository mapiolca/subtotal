<?php
/* SPDX-License-Identifier: GPL-3.0-or-later */

require __DIR__.'/bootstrap.php';

require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
dol_include_once('/subtotal/lib/subtotal.lib.php');
dol_include_once('/subtotal/class/subtotal.class.php');

$options = subtotalMaintenanceOptions();
if ($options['execute']) {
	$user = subtotalMaintenanceRequireAdmin($options['user_id']);
}

$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'propal';
$sql .= ' WHERE entity = '.((int) $options['entity']);
$sql .= ' AND total_ht + tva != total';
$sql .= ' ORDER BY rowid';
if ($options['limit'] > 0) {
	$sql .= ' LIMIT '.((int) $options['limit']);
}
$resql = $db->query($sql);
if (!$resql) {
	fwrite(STDERR, $db->lasterror().PHP_EOL);
	exit(1);
}

subtotalMaintenanceWrite($db->num_rows($resql).' proposal(s) eligible');
if (!$options['execute']) {
	subtotalMaintenanceWrite('Dry run only. Add --execute --user-id=<admin id> to recalculate.');
	exit(0);
}

$updated = 0;
while (is_object($row = $db->fetch_object($resql))) {
	$proposal = new Propal($db);
	if ($proposal->fetch((int) $row->rowid) <= 0) {
		continue;
	}
	foreach ($proposal->lines as $line) {
		if (empty($line->array_options)) {
			$line->fetch_optionals();
		}
		if (!empty($line->array_options['options_subtotal_nc']) && !TSubtotal::isModSubtotalLine($line)) {
			if (_updateLineNC($proposal->element, $proposal->id, $line->id, 1, 1) > 0) {
				$updated++;
			}
		}
	}
}
subtotalMaintenanceWrite($updated.' line block(s) recalculated.');
