<?php
/* SPDX-License-Identifier: GPL-3.0-or-later */

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
if (empty($user->admin)) {
	accessforbidden();
}
header('Location: '.dol_buildpath('/subtotal/admin/setup.php', 1), true, 302);
exit;
