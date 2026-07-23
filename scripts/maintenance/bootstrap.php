<?php
/* Copyright (C) 2026 ATM Consulting x Les Métiers du Bâtiment <developpeur@lesmetiersdubatiment.fr>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This maintenance script is CLI-only.\n");
}

if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}

$mainCandidates = array(
	dirname(__DIR__, 4).'/main.inc.php',
	dirname(__DIR__, 3).'/main.inc.php',
);
$loaded = false;
foreach ($mainCandidates as $mainCandidate) {
	if (is_readable($mainCandidate)) {
		$loaded = (bool) include $mainCandidate;
		if ($loaded) {
			break;
		}
	}
}
if (!$loaded) {
	fwrite(STDERR, "Unable to load Dolibarr main.inc.php.\n");
	exit(2);
}

/**
 * @return array{execute:bool, entity:int, limit:int, user_id:int}
 */
function subtotalMaintenanceOptions()
{
	$options = getopt('', array('execute', 'entity:', 'limit::', 'user-id::'));
	if (!isset($options['entity']) || is_array($options['entity'])) {
		fwrite(STDERR, "A positive --entity value is required.\n");
		exit(2);
	}
	$entity = (int) $options['entity'];
	if ($entity <= 0) {
		fwrite(STDERR, "A positive --entity value is required.\n");
		exit(2);
	}

	return array(
		'execute' => isset($options['execute']),
		'entity' => $entity,
		'limit' => isset($options['limit']) ? max(0, (int) $options['limit']) : 0,
		'user_id' => isset($options['user-id']) ? max(0, (int) $options['user-id']) : 0,
	);
}

/**
 * @param string $message Message.
 * @return void
 */
function subtotalMaintenanceWrite($message)
{
	if (function_exists('dol_syslog')) {
		dol_syslog('Subtotal maintenance: '.$message, LOG_INFO);
	}
	fwrite(STDOUT, $message.PHP_EOL);
}

/**
 * Load and validate the administrator used by an executing maintenance task.
 *
 * @param int $userId User ID.
 * @return User
 */
function subtotalMaintenanceRequireAdmin($userId)
{
	global $db;

	if ((int) $userId <= 0) {
		fwrite(STDERR, "--user-id is required with --execute.\n");
		exit(2);
	}

	require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
	$maintenanceUser = new User($db);
	if ($maintenanceUser->fetch((int) $userId) <= 0 || empty($maintenanceUser->admin)) {
		fwrite(STDERR, "The maintenance user must exist and be an administrator.\n");
		exit(2);
	}

	return $maintenanceUser;
}
