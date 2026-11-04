<?php
/**
 * Execute the dictionary model and native CommonObject CRUD/validation with an in-memory SQL double.
 * No database or Dolibarr configuration is modified.
 * Run: php test/freetext_model_test.php /path/to/dolibarr/htdocs
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
if (PHP_SAPI !== 'cli') exit(1);
$coreRoot = isset($argv[1]) ? realpath($argv[1]) : false;
if (!$coreRoot) exit(1);
define('DOL_DOCUMENT_ROOT', $coreRoot);
define('MAIN_DB_PREFIX', 'fixture_');
define('DOL_VERSION', getenv('SUBTOTAL_TEST_DOL_VERSION') ?: '25.0.0-alpha');
require_once $coreRoot.'/core/lib/functions.lib.php';
require_once $coreRoot.'/core/lib/functions2.lib.php';
if (is_file($coreRoot.'/core/lib/html.lib.php')) require_once $coreRoot.'/core/lib/html.lib.php';
date_default_timezone_set('UTC');
$conf = (object) array('entity' => 1, 'global' => new stdClass(), 'modules' => array(), 'syslog' => (object) array('enabled' => 0));
class FreeTextTestTranslations
{
	public $charset_output = 'UTF-8';
	public $defaultlang = 'en_US';
	public $map = array();
	public function trans($key, ...$args) { return (isset($this->map[$key]) ? $this->map[$key] : $key).($args ? ':'.implode(',', $args) : ''); }
	public function transnoentities($key, ...$args) { return $this->trans($key, ...$args); }
	public function transnoentitiesnoconv($key) { return $key === 'SeparatorDecimal' ? '.' : ($key === 'SeparatorThousand' ? ',' : $key); }
	public function load($key) { return 1; }
	public function loadLangs($keys) { return 1; }
	public function getDefaultLang() { return $this->defaultlang; }
}
$langs = new FreeTextTestTranslations();
class User {
	public $id = 1;
	public $socid = 0;
	public $admin = 0;
	public $manage = true;
	public $document = true;
	public function hasRight($module, ...$parts) { return $module === 'subtotal' ? $this->manage : $this->document; }
}
$user = new User();

/** Small SQL double; it rejects all tables outside this dictionary. Not a MySQL integration test. */
class FreeTextTestDB
{
	public $rows = array();
	public $queries = array();
	public $depth = 0;
	public $fail = '';
	private $before = array();
	private $lastId = 0;
	public function prefix() { return MAIN_DB_PREFIX; }
	public function sanitize($value, ...$args) { return $value; }
	public function escape($value) { return str_replace("'", "''", $value); }
	public function lasterror() { return 'Simulated database failure'; }
	public function lasterrno() { return 'TEST_FAILURE'; }
	public function last_insert_id($table) { return $this->lastId; }
	public function plimit($limit, $offset = 0) { return ' LIMIT '.$limit.' OFFSET '.$offset; }
	public function fetch_object($result) { return $result->valid() ? (object) $this->take($result) : false; }
	private function take($result) { $row = $result->current(); $result->next(); return $row; }
	public function free($result) { return true; }
	public function begin() {
		if ($this->fail === 'begin') return 0;
		if (!$this->depth) $this->before = $this->rows;
		$this->depth++;
		return 1;
	}
	public function commit() {
		if ($this->fail === 'commit' && $this->depth === 1) return 0;
		$this->depth--;
		return 1;
	}
	public function rollback() { $this->rows = $this->before; $this->depth = 0; return 1; }
	public function query($sql) {
		$this->queries[] = $sql;
		if (strpos($sql, MAIN_DB_PREFIX.'c_subtotal_free_text') === false) throw new RuntimeException('Unexpected table: '.$sql);
		if ($this->fail === 'query') return false;
		if (preg_match('/^INSERT INTO \S+ \((.+)\) VALUES \((.+)\)$/s', $sql, $match)) {
			$columns = explode(', ', $match[1]);
			preg_match_all("/'(?:''|[^'])*'|NULL|-?[0-9]+/s", $match[2], $tokens);
			$row = array();
			foreach ($columns as $i => $column) $row[$column] = $this->decode($tokens[0][$i]);
			$row['rowid'] = ++$this->lastId;
			$this->rows[$this->lastId] = $row;
			return true;
		}
		if (preg_match('/^UPDATE \S+ SET (.+) WHERE rowid\s*=\s*(\d+)$/s', $sql, $match)) {
			preg_match_all("/(\w+)\s*=\s*('(?:''|[^'])*'|NULL|-?[0-9]+)/s", $match[1], $pairs, PREG_SET_ORDER);
			foreach ($pairs as $pair) $this->rows[(int) $match[2]][$pair[1]] = $this->decode($pair[2]);
			return true;
		}
		$rows = array_values($this->rows);
		if (preg_match('/(?:t\.)?entity = (\d+)/', $sql, $match)) $rows = array_values(array_filter($rows, function ($row) use ($match) { return $row['entity'] == $match[1]; }));
		if (preg_match('/(?:t\.)?rowid = (\d+)/', $sql, $match)) $rows = array_values(array_filter($rows, function ($row) use ($match) { return $row['rowid'] == $match[1]; }));
		if (strpos($sql, 't.active = 1') !== false) $rows = array_values(array_filter($rows, function ($row) { return $row['active'] == 1; }));
		if (preg_match("/t.label LIKE '%(.*?)%'/i", $sql, $match)) $rows = array_values(array_filter($rows, function ($row) use ($match) { return stripos($row['label'], str_replace("''", "'", $match[1])) !== false; }));
		if (strpos($sql, 'DELETE FROM') === 0) { foreach ($rows as $row) unset($this->rows[$row['rowid']]); return true; }
		if (strpos($sql, 'COUNT(*)') !== false) return new ArrayIterator(array(array('total' => count($rows))));
		usort($rows, function ($a, $b) { return strcmp($a['label'], $b['label']); });
		if (preg_match('/LIMIT (\d+)(?: OFFSET (\d+))?/', $sql, $match)) $rows = array_slice($rows, isset($match[2]) ? (int) $match[2] : 0, (int) $match[1]);
		return new ArrayIterator($rows);
	}
	private function decode($value) { return $value === 'NULL' ? null : ($value[0] === "'" ? str_replace("''", "'", substr($value, 1, -1)) : (int) $value); }
}

require_once __DIR__.'/../class/subtotalfreetext.class.php';
$db = new FreeTextTestDB();
$checks = 0;
function checkFreeText($condition, $message) {
	global $checks, $db;
	$checks++;
	if (!$condition) {
		if (getenv('SUBTOTAL_TEST_DEBUG')) fwrite(STDERR, json_encode(array('rows' => $db->rows, 'queries' => array_slice($db->queries, -3)), JSON_PRETTY_PRINT)."\n");
		throw new RuntimeException($message);
	}
}
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
$entry = new SubtotalFreeText($db, 1);
checkFreeText(strpos(SubtotalFreeText::sanitizeContent("Été & hiver\nDeuxième ligne"), "\nDeuxième ligne") !== false, 'Plain textarea line breaks must survive');
checkFreeText($entry->mutate('create', $user, array('label' => '  ')) === -1 && !$db->rows, 'Empty labels must be rejected');
checkFreeText($entry->mutate('create', $user, array('label' => str_repeat('é', 256))) === -1 && !$db->rows, 'Labels longer than 255 characters must be rejected');
checkFreeText($entry->mutate('create', $user, array('label' => "Texte d’essai & < >", 'content' => '<p>Bonjour <strong>été</strong></p><script>alert(1)</script><img src=x onerror="alert(2)"><a href="javascript:alert(3)">x</a>', 'active' => 1)) > 0, 'Native create must succeed');
$id = $entry->id;
$content = $entry->toArray()['content'];
checkFreeText(strpos(html_entity_decode($content, ENT_QUOTES, 'UTF-8'), '<strong>été</strong>') !== false && !preg_match('/<script|onerror=|href="javascript:/i', $content), 'Native sanitizer must preserve allowed HTML only');
$copy = new SubtotalFreeText($db, 1);
checkFreeText($copy->fetch($id) > 0 && $copy->label === $entry->label, 'Native fetch must read the existing dictionary');
$original = $copy->fingerprint();
checkFreeText($copy->mutate('update', $user, array('label' => 'Modifié', 'content' => '', 'active' => 0), $original) > 0, 'Optional empty content must be accepted');
checkFreeText($copy->content === '' && $copy->active === 0 && !$copy->listEntries('', true)['rows'], 'Inactive entries must be hidden from insertion');
checkFreeText($entry->mutate('update', $user, array('label' => 'Stale overwrite'), $original) === -2 && $db->rows[$id]['label'] === 'Modifié', 'Concurrent stale update must not overwrite');
checkFreeText($entry->mutate('delete', $user, array(), $original) === -2 && isset($db->rows[$id]), 'Stale delete must not remove newer data');
checkFreeText($copy->mutate('toggle', $user, array(), $copy->fingerprint()) > 0 && $copy->active === 1, 'Activation must preserve data');
$foreign = new SubtotalFreeText($db, 2);
checkFreeText($foreign->fetch($id) === 0 && $foreign->listEntries()['total'] === 0, 'Other entities must not be readable');
$foreign->id = $id;
checkFreeText($foreign->mutate('delete', $user, array(), $copy->fingerprint()) < 0 && isset($db->rows[$id]), 'Other entities must not be mutable');
checkFreeText($foreign->mutate('create', $user, array('label' => 'Other entity')) > 0 && $db->rows[$foreign->id]['entity'] === 2, 'Creation must use explicit entity');
checkFreeText($copy->listEntries()['total'] === 1 && $copy->listEntries('Modifié')['total'] === 1 && $copy->listEntries('absent')['total'] === 0, 'Entity and label filters precede pagination');
$db->fail = 'begin';
checkFreeText($copy->mutate('delete', $user, array(), $copy->fingerprint()) === -1 && isset($db->rows[$id]), 'BEGIN failure must abort');
$db->fail = 'query';
checkFreeText($copy->mutate('delete', $user, array(), $copy->fingerprint()) === -1 && isset($db->rows[$id]), 'SQL failure must abort');
$db->fail = 'commit';
checkFreeText($copy->mutate('update', $user, array('label' => 'Uncommitted'), $copy->fingerprint()) === -1 && $db->rows[$id]['label'] === 'Modifié', 'COMMIT failure must not report success');
$db->fail = '';
$copy->fetch($id);
$db->rows[$id]['content'] = '<p>Modified by native admin</p>';
checkFreeText($copy->mutate('toggle', $user, array(), $copy->fingerprint()) === -2, 'Native administrative changes must also conflict');
$copy->fetch($id);
checkFreeText($copy->mutate('delete', $user, array(), $copy->fingerprint()) > 0 && !isset($db->rows[$id]) && isset($db->rows[$foreign->id]), 'Deletion must affect only its entity record');
checkFreeText($copy->mutate('update', $user, array('label' => 'Deleted'), $copy->fingerprint()) < 0, 'Deleted record must not be recreated on update');
restore_error_handler();
if (isset($argv[2]) && $argv[2] === '--fixtures') return;
print 'OK: '.$checks." dictionary scenarios with native CommonObject and simulated SQL/transactions.\n";
