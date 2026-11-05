<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/** Existing dictionary data, without document, extrafield or event side effects. */
class SubtotalFreeText extends CommonObject
{
	public $module = 'subtotal';
	public $element = 'subtotalfreetext';
	public $table_element = 'c_subtotal_free_text';
	public $TRIGGER_PREFIX = 'SUBTOTAL_FREETEXT';
	public $rowid;
	public $label = '';
	public $content = '';
	public $active = 1;
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'index' => 1, 'position' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 10),
		'content' => array('type' => 'text', 'label' => 'Description', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 20),
		'active' => array('type' => 'integer', 'label' => 'Active', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'default' => 1, 'position' => 30),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 40),
	);

	/**
	 * @param DoliDB $db Database.
	 * @param int $entity Authenticated entity.
	 */
	public function __construct($db, $entity)
	{
		$this->db = $db;
		$this->entity = (int) $entity;
	}

	/**
	 * @param int $id Record ID.
	 * @param string|null $ref Unused.
	 * @return int
	 */
	public function fetch($id, $ref = null)
	{
		if ((int) $id <= 0 || (int) $this->entity <= 0) return 0;
		return $this->fetchCommon((int) $id, null, ' AND t.entity = '.((int) $this->entity), 1);
	}

	/** @return string Optimistic concurrency fingerprint, also covering native-admin edits. */
	public function fingerprint()
	{
		return hash('sha256', json_encode(array((int) $this->id, (int) $this->entity, (string) $this->label, (string) $this->content, (int) $this->active)));
	}

	/** @return array{id:int,label:string,content:string,active:int,signature:string} */
	public function toArray()
	{
		return array('id' => (int) $this->id, 'label' => (string) $this->label,
			'content' => self::sanitizeContent((string) $this->content),
			'active' => (int) $this->active, 'signature' => $this->fingerprint());
	}

	/**
	 * Text formatting policy shared by stored templates, previews and inserted copies.
	 * Keep document text/lists/tables; no remote resources, navigation, CSS or executable attributes.
	 * Both native filters exist on the historical Dolibarr 16 baseline.
	 * @param string $content HTML or plain text.
	 * @return string
	 */
	public static function sanitizeContent($content)
	{
		if (strpos($content, '<') === false) return $content;
		if (!class_exists('DOMDocument')) return dol_escape_htmltag($content, 1, 1);
		$tags = array('p', 'br', 'b', 'strong', 'em', 'i', 'u', 's', 'strike', 'ul', 'ol', 'li', 'blockquote',
			'span', 'div', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'sup', 'sub', 'hr', 'pre');
		// The native attribute filter needs its wrapper's id while serializing the fragment.
		$previousErrors = libxml_use_internal_errors(true);
		try {
			return dol_string_onlythesehtmlattributes(dol_string_onlythesehtmltags($content, 1, 1, 1, 0, $tags), array('id', 'colspan', 'rowspan', 'align'));
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors($previousErrors);
		}
	}

	/**
	 * @param string $search Label search.
	 * @param bool $activeOnly Only selectable entries.
	 * @param int $limit Page size (0 for the active selector).
	 * @param int $offset Offset.
	 * @param string $sortfield Sort column.
	 * @param string $sortorder Sort direction.
	 * @return array{rows:array<int,array{id:int,label:string,content:string,active:int,signature:string}>,total:int}|false
	 */
	public function listEntries($search = '', $activeOnly = false, $limit = 20, $offset = 0, $sortfield = 'label', $sortorder = 'ASC')
	{
		if ((int) $this->entity <= 0) return false;
		$where = ' WHERE t.entity = '.((int) $this->entity);
		if ($activeOnly) $where .= ' AND t.active = 1';
		if ($search !== '') $where .= natural_search('t.label', $search);
		$count = $this->db->query('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.$this->table_element.' AS t'.$where);
		if (!$count || !is_object($total = $this->db->fetch_object($count))) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$sortfield = in_array($sortfield, array('label', 'active'), true) ? $sortfield : 'label';
		$sortorder = $sortorder === 'DESC' ? 'DESC' : 'ASC';
		$sql = 'SELECT t.rowid, t.label, t.content, t.active, t.entity FROM '.MAIN_DB_PREFIX.$this->table_element.' AS t'.$where;
		$sql .= ' ORDER BY t.'.$sortfield.' '.$sortorder.', t.rowid ASC';
		if ($limit > 0) $sql .= $this->db->plimit((int) $limit, max(0, (int) $offset));
		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$rows = array();
		while (is_object($row = $this->db->fetch_object($res))) {
			$entry = new self($this->db, $this->entity);
			$entry->id = (int) $row->rowid;
			$entry->label = (string) $row->label;
			$entry->content = (string) $row->content;
			$entry->active = (int) $row->active;
			$rows[] = $entry->toArray();
		}
		return array('rows' => $rows, 'total' => (int) $total->total);
	}

	/**
	 * Lock, validate and mutate the existing dictionary, without unrelated ERP effects.
	 * @param string $operation create|update|toggle|delete.
	 * @param User $user Actor.
	 * @param array{label?:string,content?:string,active?:int} $values Validated input.
	 * @param string $signature Original fingerprint.
	 * @return int Positive on success, -2 conflict, -1 error.
	 */
	public function mutate($operation, $user, array $values, $signature = '')
	{
		global $langs;
		if ((int) $this->entity <= 0 || !in_array($operation, array('create', 'update', 'toggle', 'delete'), true)) return -1;
		if ($this->db->begin() <= 0) {
			$this->error = $langs->trans('SubtotalFreeTextSaveFailed');
			return -1;
		}
		if ($operation !== 'create') {
			$lock = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.$this->table_element.' WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $this->entity).' FOR UPDATE');
			if (!$lock) {
				$this->error = $langs->trans('SubtotalFreeTextSaveFailed');
				$this->db->rollback();
				return -1;
			}
			$fetched = $this->fetch($this->id);
			if ($fetched <= 0) {
				$this->error = $langs->trans($fetched === 0 ? 'SubtotalFreeTextConflict' : 'SubtotalFreeTextSaveFailed');
				$this->db->rollback();
				return $fetched === 0 ? -2 : -1;
			}
			if ($signature === '' || !hash_equals($this->fingerprint(), $signature)) {
				$this->error = $langs->trans('SubtotalFreeTextConflict');
				$this->db->rollback();
				return -2;
			}
		}
		$this->oldcopy = clone $this;
		if ($operation === 'delete') {
			// deleteCommon() also removes ECM, links and document directories: a dictionary has none.
			$result = $this->db->query('DELETE FROM '.MAIN_DB_PREFIX.$this->table_element.' WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $this->entity)) ? 1 : -1;
		} else {
			if ($operation === 'toggle') {
				$this->active = $this->active ? 0 : 1;
			} else {
				$this->label = trim(isset($values['label']) ? $values['label'] : '');
				$this->content = self::sanitizeContent(isset($values['content']) ? $values['content'] : '');
				$this->active = isset($values['active']) && $values['active'] === 0 ? 0 : 1;
			}
			// Historical validateField() reads both the keyed field and its top-level type.
			$validationFields = $this->fields;
			if (version_compare(DOL_VERSION, '20.0.0', '<')) $validationFields += array('type' => $this->fields['label']['type']);
			if (!$this->validateField($validationFields, 'label', $this->label)) {
				$this->error = $this->getFieldError('label');
				$this->db->rollback();
				return -1;
			}
			// Native dictionaries do not emit document CRUD/Agenda events or create extrafields.
			$result = $operation === 'create' ? $this->createCommon($user, 1) : $this->updateCommon($user, 1);
		}
		if ($result <= 0) {
			if (!$this->error) $this->error = $langs->trans('SubtotalFreeTextSaveFailed');
			$this->db->rollback();
			return -1;
		}
		if ($this->db->commit() <= 0) {
			$this->db->rollback();
			$this->error = $langs->trans('SubtotalFreeTextSaveFailed');
			return -1;
		}
		return (int) $result;
	}
}
