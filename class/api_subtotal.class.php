<?php
/* Copyright (C) 2026 ATM Consulting x Les Métiers du Bâtiment <developpeur@lesmetiersdubatiment.fr>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use Luracast\Restler\RestException;

dol_include_once('/subtotal/class/subtotal.class.php');
dol_include_once('/subtotal/class/subtotalaccess.class.php');
dol_include_once('/subtotal/lib/subtotal.lib.php');

/**
 * REST API for Subtotal.
 *
 * @access protected
 * @class DolibarrApiAccess {@requires user,external}
 */
class Subtotal extends DolibarrApi
{
	const TYPE_PROPAL = 'propal';
	const TYPE_ORDER = 'order';
	const TYPE_ORDER_SUPPLIER = 'ordsup';
	const TYPE_INVOICE = 'invoice';
	const TYPE_INVOICE_SUPPLIER = 'invsup';

	/**
	 * Constructor used by Restler.
	 */
	public function __construct()
	{
		global $db;

		$this->db = $db;
	}

	/**
	 * Get the amount represented by a Subtotal line.
	 *
	 * @param string $elementtype propal|order|ordsup|invoice|invsup.
	 * @param int    $idline Line ID.
	 * @return float|int
	 *
	 * @url GET {elementtype}/{idline}
	 *
	 * @throws RestException
	 */
	public function getTotalLine($elementtype, $idline = 0)
	{
		global $langs, $user;

		$langs->load('subtotal@subtotal');
		if (!isModEnabled('subtotal')) {
			throw new RestException(503, $langs->transnoentities('ModuleNotEnabled'));
		}
		if (!is_numeric($idline) || (int) $idline <= 0) {
			throw new RestException(400, $langs->transnoentities('SubtotalApiInvalidLineId'));
		}

		$definition = $this->getApiDefinition($elementtype);
		if (!is_array($definition)) {
			throw new RestException(400, $langs->transnoentities('SubtotalApiUnsupportedElement'));
		}

		dol_include_once($definition['file']);
		$lineClass = $definition['line_class'];
		if (!class_exists($lineClass)) {
			throw new RestException(503, $langs->transnoentities('SubtotalApiUnavailableClass'));
		}

		$line = new $lineClass($this->db);
		if ($line->fetch((int) $idline) <= 0) {
			throw new RestException(404, $langs->transnoentities('SubtotalLineNotFound'));
		}

		$parentField = $definition['parent_field'];
		$parentId = !empty($line->{$parentField}) ? (int) $line->{$parentField} : 0;
		$object = SubtotalAccess::fetchObject($definition['element'], $parentId);
		if (!is_object($object)) {
			throw new RestException(404, $langs->transnoentities('SubtotalParentNotFound'));
		}
		if (!SubtotalAccess::canRead($user, $object)) {
			throw new RestException(403, $langs->transnoentities('NotEnoughPermissions'));
		}

		$parentLine = SubtotalAccess::findLine($object, (int) $idline);
		if (!is_object($parentLine)) {
			throw new RestException(404, $langs->transnoentities('SubtotalLineNotFound'));
		}
		if (!TSubtotal::isSubtotal($parentLine)) {
			throw new RestException(400, $langs->transnoentities('SubtotalApiLineIsNotSubtotal'));
		}

		return price2num(getTotalLineFromObject($object, $parentLine), 'MT');
	}

	/**
	 * @param string $elementtype Public API type.
	 * @return array<string, string>|null
	 */
	private function getApiDefinition($elementtype)
	{
		$definitions = array(
			self::TYPE_PROPAL => array('element' => 'propal', 'file' => '/comm/propal/class/propal.class.php', 'line_class' => 'PropaleLigne', 'parent_field' => 'fk_propal'),
			self::TYPE_ORDER => array('element' => 'commande', 'file' => '/commande/class/commande.class.php', 'line_class' => 'OrderLine', 'parent_field' => 'fk_commande'),
			self::TYPE_ORDER_SUPPLIER => array('element' => 'order_supplier', 'file' => '/fourn/class/fournisseur.commande.class.php', 'line_class' => 'CommandeFournisseurLigne', 'parent_field' => 'fk_commande'),
			self::TYPE_INVOICE => array('element' => 'facture', 'file' => '/compta/facture/class/facture.class.php', 'line_class' => 'FactureLigne', 'parent_field' => 'fk_facture'),
			self::TYPE_INVOICE_SUPPLIER => array('element' => 'invoice_supplier', 'file' => '/fourn/class/fournisseur.facture.class.php', 'line_class' => 'SupplierInvoiceLine', 'parent_field' => 'fk_facture_fourn'),
		);
		return isset($definitions[$elementtype]) ? $definitions[$elementtype] : null;
	}
}
