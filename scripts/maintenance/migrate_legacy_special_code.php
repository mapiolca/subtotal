<?php
/* SPDX-License-Identifier: GPL-3.0-or-later */

require __DIR__.'/bootstrap.php';

$options = subtotalMaintenanceOptions();
if ($options['execute']) {
	subtotalMaintenanceRequireAdmin($options['user_id']);
}
$targets = array(
	array('detail' => 'propaldet', 'parent' => 'propal', 'foreign_key' => 'fk_propal'),
	array('detail' => 'commandedet', 'parent' => 'commande', 'foreign_key' => 'fk_commande'),
	array('detail' => 'facturedet', 'parent' => 'facture', 'foreign_key' => 'fk_facture'),
);

$db->begin();
$error = 0;
foreach ($targets as $target) {
	$sqlCount = 'SELECT COUNT(d.rowid) AS nb';
	$sqlCount .= ' FROM '.MAIN_DB_PREFIX.$target['detail'].' AS d';
	$sqlCount .= ' INNER JOIN '.MAIN_DB_PREFIX.$target['parent'].' AS p ON p.rowid = d.'.$target['foreign_key'];
	$sqlCount .= ' WHERE d.special_code = 1790 AND p.entity = '.((int) $options['entity']);
	$resql = $db->query($sqlCount);
	if (!$resql || !is_object($row = $db->fetch_object($resql))) {
		$error++;
		break;
	}
	subtotalMaintenanceWrite($target['detail'].': '.((int) $row->nb).' line(s) eligible');

	if ($options['execute'] && (int) $row->nb > 0) {
		$sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX.$target['detail'].' AS d';
		$sqlUpdate .= ' INNER JOIN '.MAIN_DB_PREFIX.$target['parent'].' AS p ON p.rowid = d.'.$target['foreign_key'];
		$sqlUpdate .= ' SET d.special_code = 104777';
		$sqlUpdate .= ' WHERE d.special_code = 1790 AND p.entity = '.((int) $options['entity']);
		if (!$db->query($sqlUpdate)) {
			$error++;
			break;
		}
	}
}

if ($error) {
	$db->rollback();
	fwrite(STDERR, $db->lasterror().PHP_EOL);
	exit(1);
}
if ($options['execute']) {
	$db->commit();
	subtotalMaintenanceWrite('Migration committed.');
} else {
	$db->rollback();
	subtotalMaintenanceWrite('Dry run only. Add --execute to apply the migration.');
}
