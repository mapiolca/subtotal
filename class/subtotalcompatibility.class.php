<?php
/* Copyright (C) 2026 ATM Consulting x Les Métiers du Bâtiment <developpeur@lesmetiersdubatiment.fr>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Central compatibility information for Subtotal.
 *
 * @phpstan-type CompatibilityFeature array{
 *     label: string,
 *     description: string,
 *     min_dolibarr: string,
 *     core_available_from: string,
 *     module_available_from: string,
 *     min_php: string,
 *     compatibility_check: string,
 *     available: bool,
 *     reason: string
 * }
 */
class SubtotalCompatibility
{
	const MIN_DOLIBARR_VERSION = '16.0.0';
	const RECOMMENDED_DOLIBARR_VERSION = '20.0.0';
	const MIN_PHP_VERSION = '7.0.0';
	const RECOMMENDED_PHP_VERSION = '8.0.0';

	/**
	 * @param string $version Minimum version.
	 * @return bool
	 */
	public static function isDolibarrVersionAtLeast($version)
	{
		return version_compare(DOL_VERSION, $version, '>=');
	}

	/**
	 * @param string $version Minimum version.
	 * @return bool
	 */
	public static function isPhpVersionAtLeast($version)
	{
		return version_compare(PHP_VERSION, $version, '>=');
	}

	/**
	 * @return array<string, CompatibilityFeature>
	 */
	public static function getCompatibilityFeatures()
	{
		$baseAvailable = self::isDolibarrVersionAtLeast(self::MIN_DOLIBARR_VERSION)
			&& self::isPhpVersionAtLeast(self::MIN_PHP_VERSION);

		return array(
			'commercial_document_hooks' => self::feature(
				'CompatibilityFeatureDocumentHooks',
				'CompatibilityFeatureDocumentHooksDescription',
				'16.0.0',
				"version_compare(DOL_VERSION, '16.0.0', '>=')",
				$baseAvailable
			),
			'protected_ajax' => self::feature(
				'CompatibilityFeatureProtectedAjax',
				'CompatibilityFeatureProtectedAjaxDescription',
				'16.0.0',
				"version_compare(DOL_VERSION, '16.0.0', '>=')",
				$baseAvailable
			),
			'rest_api' => self::feature(
				'CompatibilityFeatureRestApi',
				'CompatibilityFeatureRestApiDescription',
				'16.0.0',
				"class_exists('DolibarrApi') && class_exists('Luracast\\\\Restler\\\\RestException')",
				$baseAvailable
			),
			'multientity_documents' => self::feature(
				'CompatibilityFeatureMultientityDocuments',
				'CompatibilityFeatureMultientityDocumentsDescription',
				'16.0.0',
				'isset($conf->{$module}->multidir_output[$object->entity])',
				$baseAvailable
			),
			'native_hook_parent' => array(
				'label' => 'CompatibilityFeatureHookParent',
				'description' => 'CompatibilityFeatureHookParentDescription',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'core_available_from' => self::RECOMMENDED_DOLIBARR_VERSION,
				'module_available_from' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'compatibility_check' => "!is_subclass_of('ActionsSubtotal', 'CommonHookActions')",
				'available' => $baseAvailable,
				'reason' => $baseAvailable ? '' : 'CompatibilityReasonBaseVersion',
			),
		);
	}

	/**
	 * @param string $label Translation key.
	 * @param string $description Translation key.
	 * @param string $coreAvailableFrom Core version.
	 * @param string $check Description of check.
	 * @param bool   $available Availability.
	 * @return CompatibilityFeature
	 */
	private static function feature($label, $description, $coreAvailableFrom, $check, $available)
	{
		return array(
			'label' => $label,
			'description' => $description,
			'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
			'core_available_from' => $coreAvailableFrom,
			'module_available_from' => self::MIN_DOLIBARR_VERSION,
			'min_php' => self::MIN_PHP_VERSION,
			'compatibility_check' => $check,
			'available' => $available,
			'reason' => $available ? '' : 'CompatibilityReasonBaseVersion',
		);
	}

	/**
	 * @param string $featureCode Feature code.
	 * @return bool
	 */
	public static function isFeatureAvailable($featureCode)
	{
		$features = self::getCompatibilityFeatures();
		return isset($features[$featureCode]) && !empty($features[$featureCode]['available']);
	}

	/**
	 * @return array<string, CompatibilityFeature>
	 */
	public static function getUnavailableFeatures()
	{
		$unavailable = array();
		foreach (self::getCompatibilityFeatures() as $code => $feature) {
			if (empty($feature['available'])) {
				$unavailable[$code] = $feature;
			}
		}
		return $unavailable;
	}
}
