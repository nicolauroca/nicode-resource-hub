<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Internal CLI bridge: credentials arrive through stdin, never OS arguments.
if (PHP_SAPI !== 'cli' || $argc !== 2 || !is_file($argv[1] . '/installation/joomla.php')) {
    fwrite(STDERR, "Usage: php install-site.php UNINSTALLED_JOOMLA_DIRECTORY\n");
    exit(2);
}
$site = realpath($argv[1]);
$args = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (!is_array($args) || ($args[1] ?? '') !== 'install') {
    exit(2);
}
$_SERVER['argv'] = $args;
$_SERVER['argc'] = count($args);
$argv = $args;
$argc = count($args);
require $site . '/installation/joomla.php';
