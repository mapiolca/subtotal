<?php
/* SPDX-License-Identifier: GPL-3.0-or-later */

require __DIR__.'/bootstrap.php';

require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

$options = subtotalMaintenanceOptions();
if ($options['execute']) {
	$user = subtotalMaintenanceRequireAdmin($options['user_id']);
}
$targets = array(
	array('detail' => 'propaldet', 'parent' => 'propal', 'foreign_key' => 'fk_propal', 'class' => 'PropaleLigne'),
	array('detail' => 'commandedet', 'parent' => 'commande', 'foreign_key' => 'fk_commande', 'class' => 'OrderLine'),
	array('detail' => 'facturedet', 'parent' => 'facture', 'foreign_key' => 'fk_facture', 'class' => 'FactureLigne'),
);

$error = 0;
$updated = 0;
foreach ($targets as $target) {
	$sql = 'SELECT d.rowid FROM '.MAIN_DB_PREFIX.$target['detail'].' AS d';
	$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.$target['parent'].' AS p ON p.rowid = d.'.$target['foreign_key'];
	$sql .= ' WHERE d.special_code = 104777 AND d.qty != 50 AND d.product_type = 9';
	$sql .= " AND (d.label = '' OR d.label IS NULL)";
	$sql .= ' AND p.entity = '.((int) $options['entity']);
	$sql .= ' ORDER BY d.rowid';
	if ($options['limit'] > 0) {
		$sql .= ' LIMIT '.((int) $options['limit']);
	}

	$resql = $db->query($sql);
	if (!$resql) {
		$error++;
		break;
	}
	subtotalMaintenanceWrite($target['detail'].': '.$db->num_rows($resql).' line(s) eligible');
	if (!$options['execute']) {
		continue;
	}

	$db->begin();
	while (is_object($row = $db->fetch_object($resql))) {
		$className = $target['class'];
		$line = new $className($db);
		if ($line->fetch((int) $row->rowid) <= 0) {
			$error++;
			break;
		}
		$line->label = trim(strip_tags(!empty($line->desc) ? $line->desc : (isset($line->description) ? $line->description : '')));
		$line->desc = '';
		$result = $target['detail'] === 'propaldet' ? $line->update(1) : $line->update($user, 1);
		if ($result <= 0) {
			$error++;
			break;
		}
		$updated++;
	}
	if ($error) {
		$db->rollback();
		break;
	}
	$db->commit();
}

if ($error) {
	fwrite(STDERR, "Migration failed; the current table transaction was rolled back.\n");
	exit(1);
}
subtotalMaintenanceWrite($options['execute'] ? $updated.' line(s) updated.' : 'Dry run only. Add --execute to update labels.');
