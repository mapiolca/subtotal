<?php
/* Copyright (C) 2026 ATM Consulting x Les Métiers du Bâtiment <developpeur@lesmetiersdubatiment.fr>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Central native-rights and Multicompany access policy for Subtotal.
 */
class SubtotalAccess
{
	/**
	 * Normalize public, API and class names to Dolibarr object elements.
	 *
	 * @param string $element Input element.
	 * @return string
	 */
	public static function normalizeElement($element)
	{
		$normalized = strtolower(trim((string) $element));
		$aliases = array(
			'propale' => 'propal',
			'order' => 'commande',
			'ordsup' => 'order_supplier',
			'commandefournisseur' => 'order_supplier',
			'invoice' => 'facture',
			'invsup' => 'invoice_supplier',
			'facturefournisseur' => 'invoice_supplier',
			'supplierproposal' => 'supplier_proposal',
			'facturerec' => 'facturerec',
			'expedition' => 'shipping',
			'deliverynote' => 'delivery',
		);
		return isset($aliases[$normalized]) ? $aliases[$normalized] : $normalized;
	}

	/**
	 * @param string $element Object element.
	 * @return array<string, mixed>|null
	 */
	public static function getObjectDefinition($element)
	{
		$element = self::normalizeElement($element);
		$definitions = array(
			'propal' => array('class' => 'Propal', 'file' => '/comm/propal/class/propal.class.php', 'read' => array('propal', 'lire'), 'write' => array('propal', 'creer')),
			'supplier_proposal' => array('class' => 'SupplierProposal', 'file' => '/supplier_proposal/class/supplier_proposal.class.php', 'read' => array('supplier_proposal', 'lire'), 'write' => array('supplier_proposal', 'creer')),
			'commande' => array('class' => 'Commande', 'file' => '/commande/class/commande.class.php', 'read' => array('commande', 'lire'), 'write' => array('commande', 'creer')),
			'order_supplier' => array('class' => 'CommandeFournisseur', 'file' => '/fourn/class/fournisseur.commande.class.php', 'read' => array('fournisseur', 'commande', 'lire'), 'write' => array('fournisseur', 'commande', 'creer')),
			'facture' => array('class' => 'Facture', 'file' => '/compta/facture/class/facture.class.php', 'read' => array('facture', 'lire'), 'write' => array('facture', 'creer')),
			'invoice_supplier' => array('class' => 'FactureFournisseur', 'file' => '/fourn/class/fournisseur.facture.class.php', 'read' => array('fournisseur', 'facture', 'lire'), 'write' => array('fournisseur', 'facture', 'creer')),
			'facturerec' => array('class' => 'FactureRec', 'file' => '/compta/facture/class/facture-rec.class.php', 'read' => array('facture', 'lire'), 'write' => array('facture', 'creer')),
			'shipping' => array('class' => 'Expedition', 'file' => '/expedition/class/expedition.class.php', 'read' => array('expedition', 'lire'), 'write' => array('expedition', 'creer')),
			'delivery' => array('class' => 'Delivery', 'file' => '/delivery/class/delivery.class.php', 'read' => array('expedition', 'lire'), 'write' => array('expedition', 'creer')),
		);
		return isset($definitions[$element]) ? $definitions[$element] : null;
	}

	/**
	 * @param string $element Object element.
	 * @param int    $id Object ID.
	 * @return CommonObject|null
	 */
	public static function fetchObject($element, $id)
	{
		global $db;

		$id = (int) $id;
		$definition = self::getObjectDefinition($element);
		if ($id <= 0 || !is_array($definition)) {
			return null;
		}

		dol_include_once($definition['file']);
		$className = $definition['class'];
		if (!class_exists($className)) {
			return null;
		}

		$object = new $className($db);
		if ($object->fetch($id) <= 0) {
			return null;
		}
		return $object;
	}

	/**
	 * @param User $user User.
	 * @return bool
	 */
	public static function isFullAdmin($user)
	{
		if (!is_object($user)) {
			return false;
		}
		if (!empty($user->admin)) {
			return true;
		}
		return $user->hasRight('multicompany', 'admin');
	}

	/**
	 * @param User         $user User.
	 * @param CommonObject $object Object.
	 * @return bool
	 */
	public static function canRead($user, $object)
	{
		return self::canDo($user, $object, 'read');
	}

	/**
	 * @param User         $user User.
	 * @param CommonObject $object Object.
	 * @return bool
	 */
	public static function canWrite($user, $object)
	{
		return self::canDo($user, $object, 'write');
	}

	/**
	 * Check whether the parent object accepts document-line mutations.
	 *
	 * @param CommonObject $object Object.
	 * @return bool
	 */
	public static function isEditable($object)
	{
		if (!is_object($object)) {
			return false;
		}
		$status = isset($object->status) ? (int) $object->status : (isset($object->statut) ? (int) $object->statut : 0);
		return $status === 0;
	}

	/**
	 * @param User         $user User.
	 * @param CommonObject $object Object.
	 * @param string       $action read|write.
	 * @return bool
	 */
	public static function canDo($user, $object, $action)
	{
		if (!is_object($user) || !is_object($object) || !self::isObjectEntityAllowed($object)) {
			return false;
		}
		if (!self::externalUserCanAccess($user, $object)) {
			return false;
		}
		if (self::isFullAdmin($user)) {
			return true;
		}

		$definition = self::getObjectDefinition(isset($object->element) ? $object->element : '');
		if (!is_array($definition)) {
			return false;
		}
		$right = $action === 'write' ? $definition['write'] : $definition['read'];
		if (count($right) === 2) {
			return $user->hasRight($right[0], $right[1]);
		}
		return $user->hasRight($right[0], $right[1], $right[2]);
	}

	/**
	 * @param CommonObject $object Object.
	 * @return bool
	 */
	public static function isObjectEntityAllowed($object)
	{
		global $conf;

		$objectEntity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
		if ($objectEntity <= 0) {
			return false;
		}

		if (!function_exists('getEntity')) {
			return $objectEntity === (int) $conf->entity;
		}

		$element = !empty($object->table_element) ? $object->table_element : $object->element;
		$entityList = (string) getEntity($element);
		$allowedEntities = array_map('intval', array_filter(explode(',', $entityList), 'strlen'));
		return in_array($objectEntity, $allowedEntities, true);
	}

	/**
	 * @param User         $user User.
	 * @param CommonObject $object Object.
	 * @return bool
	 */
	private static function externalUserCanAccess($user, $object)
	{
		if (empty($user->socid)) {
			return true;
		}
		$objectSocid = !empty($object->socid) ? (int) $object->socid : (!empty($object->fk_soc) ? (int) $object->fk_soc : 0);
		return $objectSocid > 0 && $objectSocid === (int) $user->socid;
	}

	/**
	 * @param CommonObject $object Parent object.
	 * @param int          $lineId Line ID.
	 * @return CommonObjectLine|null
	 */
	public static function findLine($object, $lineId)
	{
		$lineId = (int) $lineId;
		if ($lineId <= 0 || empty($object->lines) || !is_array($object->lines)) {
			return null;
		}
		foreach ($object->lines as $line) {
			$id = !empty($line->id) ? (int) $line->id : (!empty($line->rowid) ? (int) $line->rowid : 0);
			if ($id === $lineId) {
				return $line;
			}
		}
		return null;
	}
}
