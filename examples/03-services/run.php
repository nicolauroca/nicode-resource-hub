<?php

/** @license GPL-2.0-or-later */

use Joomla\DI\Container;
use Joomla\DI\Exception\KeyNotFoundException;
use Nicode\Lesson\Services\ResourceLabel;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$joomla = $argv[1] ?? '';
$autoload = rtrim($joomla, '/\\') . '/libraries/vendor/autoload.php';
if ($joomla === '' || !is_file($autoload)) {
    fwrite(STDERR, "Usage: php run.php /absolute/path/to/extracted-joomla\n");
    exit(2);
}

$loader = require $autoload;
$loader->addPsr4('Nicode\\Lesson\\Services\\', __DIR__ . '/src');

$checks = 0;
$check = static function (string $name, bool $passed) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException('FAIL ' . $name);
    }
    ++$checks;
    echo 'PASS ' . $name . PHP_EOL;
};

$check('class_name_does_not_load_file', !class_exists(ResourceLabel::class, false));
$check('psr4_loads_class', class_exists(ResourceLabel::class));
$check('unknown_class_is_not_invented', !class_exists('Nicode\\Lesson\\Services\\Missing'));

$provider = require __DIR__ . '/services/provider.php';
$container = new Container();
$container->set('resource.prefix', 'Resource');
$check('service_absent_before_registration', !$container->has(ResourceLabel::class));
$container->registerServiceProvider($provider);
$check('provider_registers_service', $container->has(ResourceLabel::class));
$first = $container->get(ResourceLabel::class);
$check('constructor_receives_dependency', $first->forTitle('Joomla manual') === 'Resource: Joomla manual');
$check('shared_instance_reused', $first === $container->get(ResourceLabel::class));

$other = new Container();
$other->set('resource.prefix', 'Learning');
$other->registerServiceProvider($provider);
$second = $other->get(ResourceLabel::class);
$check('second_container_uses_own_dependency', $second->forTitle('Joomla manual') === 'Learning: Joomla manual');
$check('containers_do_not_share_instance', $first !== $second);

$incomplete = new Container();
$incomplete->registerServiceProvider($provider);
$check('registration_does_not_resolve_dependency', $incomplete->has(ResourceLabel::class));
$missingDependency = false;
try {
    $incomplete->get(ResourceLabel::class);
} catch (KeyNotFoundException $error) {
    $missingDependency = true;
}
$check('missing_dependency_rejected_on_get', $missingDependency);
echo 'Completed ' . $checks . ' checks.' . PHP_EOL;
