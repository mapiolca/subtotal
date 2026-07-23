<?php
/* Copyright (C) 2026 ATM Consulting x Les Métiers du Bâtiment <developpeur@lesmetiersdubatiment.fr>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Resolve a native module document base directory for the object's owning entity.
 *
 * @param CommonObject $object Object.
 * @param string       $module Native module configuration key.
 * @return string
 */
function subtotalPdfGetOutputBase($object, $module)
{
	global $conf;

	if (!is_object($object) || empty($module) || empty($conf->{$module})) {
		return '';
	}
	if (function_exists('getMultidirOutput')) {
		$outputBase = getMultidirOutput($object, $module, 0);
		if (is_string($outputBase) && $outputBase !== '' && strpos($outputBase, 'error-') !== 0) {
			return rtrim($outputBase, '/\\');
		}
	}
	$entity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
	if (!empty($conf->{$module}->multidir_output[$entity])) {
		return rtrim($conf->{$module}->multidir_output[$entity], '/\\');
	}
	return !empty($conf->{$module}->dir_output) ? rtrim($conf->{$module}->dir_output, '/\\') : '';
}

/**
 * Resolve the company document directory for an entity.
 *
 * @param int $entity Entity.
 * @return string
 */
function subtotalPdfGetCompanyOutputBase($entity)
{
	global $conf;

	$entity = (int) $entity;
	if ($entity > 0 && !empty($conf->mycompany->multidir_output[$entity])) {
		return $conf->mycompany->multidir_output[$entity];
	}
	return !empty($conf->mycompany->dir_output) ? $conf->mycompany->dir_output : '';
}

/**
 * Reserve a conservative footer area before content rendering.
 *
 * @param float $bottomMargin PDF bottom margin.
 * @return float
 */
function subtotalPdfGetFooterHeight($bottomMargin)
{
	$footerHeight = (float) $bottomMargin + 22.0;
	$footerHeight = max($footerHeight, (float) $bottomMargin + 8.0 + (float) getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5));
	if (getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS')) {
		$footerHeight += 6.0;
	}
	return $footerHeight;
}
