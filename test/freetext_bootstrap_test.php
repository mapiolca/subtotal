<?php
/**
 * Execute the controller's bootstrap under normal/custom/missing layouts using a fake main.inc.php.
 * This checks path resolution only, not native authentication or CSRF.
 * Run: php test/freetext_bootstrap_test.php
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
if (PHP_SAPI !== 'cli') exit(1);
$base = __DIR__.'/.bootstrap-freetext-'.getmypid();
$controller = file_get_contents(__DIR__.'/../script/freetext.php');
$bootstrap = substr($controller, 0, strpos($controller, "require_once __DIR__.'/../class/subtotalfreetext.class.php'"));
$files = array(); $directories = array();
try {
	foreach (array('normal', 'custom', 'missing') as $layout) {
		$site = $base.'/'.$layout;
		$module = $site.($layout === 'custom' ? '/custom' : '').'/subtotal';
		$script = $module.'/script/freetext.php';
		for ($dir = dirname($script); !is_dir($dir); $dir = dirname($dir)) $directories[$dir] = $dir;
		mkdir(dirname($script), 0700, true);
		file_put_contents($script, $bootstrap."print 'success';\n"); $files[] = $script;
		if ($layout !== 'missing') { file_put_contents($site.'/main.inc.php', "<?php print '".$layout.":'; return 1;"); $files[] = $site.'/main.inc.php'; }
		$lines = array();
		exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script), $lines, $status);
		$expected = $layout === 'missing' ? 'Include of main fails' : $layout.':success';
		if ($status !== 0 || implode("\n", $lines) !== $expected) throw new RuntimeException('Bootstrap failed: '.$layout);
	}
} finally {
	// Only files/directories explicitly created above, within this test's private root.
	foreach ($files as $file) if (is_file($file)) unlink($file);
	usort($directories, function ($a, $b) { return strlen($b) - strlen($a); });
	foreach ($directories as $directory) if (is_dir($directory)) rmdir($directory);
}
print "OK: normal/custom bootstraps and clean refusal without an environment.\n";
