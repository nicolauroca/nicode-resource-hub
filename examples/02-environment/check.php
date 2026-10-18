<?php

/** @license GPL-2.0-or-later */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$joomla = $argv[1] ?? '';
if ($joomla === '' || !is_dir($joomla)) {
    fwrite(STDERR, "Usage: php check.php /absolute/path/to/extracted-joomla\n");
    exit(2);
}

$checks = 0;
$check = static function (string $label, bool $passed) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException('FAIL ' . $label);
    }
    ++$checks;
    echo 'PASS ' . $label . PHP_EOL;
};

try {
    $check('PHP checkpoint 8.4.26', PHP_VERSION === '8.4.26');
    foreach (['json', 'simplexml', 'dom', 'zlib', 'gd', 'mbstring', 'pdo_mysql'] as $extension) {
        $check('extension ' . $extension, extension_loaded($extension));
    }
    $check('no additional ini files', !php_ini_scanned_files());
    $check('CLI errors visible', (bool) ini_get('display_errors'));
    $check('uninstalled Joomla tree', !is_file($joomla . '/configuration.php'));
    $manifest = $joomla . '/administrator/manifests/files/joomla.xml';
    $check('Joomla manifest exists', is_file($manifest));
    $xml = simplexml_load_file($manifest);
    $check('Joomla checkpoint 6.1.4', $xml !== false && (string) $xml->version === '6.1.4');
    $autoload = $joomla . '/libraries/vendor/autoload.php';
    $check('packaged dependency loader exists', is_file($autoload));
    require $autoload;
    $check('Joomla DI class loads', class_exists(\Joomla\DI\Container::class));
    $container = new \Joomla\DI\Container();
    $container->set('lesson.check', 'ready');
    $check('Joomla container executes', $container->get('lesson.check') === 'ready');
    echo 'PASS total=' . $checks . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
