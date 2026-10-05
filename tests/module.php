<?php

/** Run with php tests/module.php /absolute/path/to/joomla. Does not write to Joomla. */

define('_JEXEC', 1);
require rtrim($argv[1], '/\\') . '/libraries/vendor/autoload.php';
require __DIR__ . '/../extensions/mod_nicoderesources/src/Helper/ResourceHelper.php';

use Joomla\Registry\Registry;
use Nicode\Module\Resources\Site\Helper\ResourceHelper;

$checks = [];
$check = static function (string $name, bool $passed) use (&$checks): void {
    $checks[$name] = $passed;
    if (!$passed) {
        throw new RuntimeException($name);
    }
};
$params = ['resource_title' => 'Joomla manual', 'resource_url' => 'https://manual.joomla.org/?a=1&b=2'];
$resource = ResourceHelper::fromParams(new Registry($params));
$check('configured_https_resource', $resource['title'] === 'Joomla manual');
foreach (['', 'javascript:alert(1)', 'data:text/html,test', '//example.org', '/relative', 'http://example.org', 'https://user:pass@example.org', "https://example.org/\npath", 'https://'] as $url) {
    $check('reject_url_' . count($checks), ResourceHelper::fromParams(new Registry(array_replace($params, ['resource_url' => $url]))) === null);
}
$check('reject_blank_title', ResourceHelper::fromParams(new Registry(array_replace($params, ['resource_title' => '  ']))) === null);
$check('reject_non_scalar', ResourceHelper::fromParams(new Registry(array_replace($params, ['resource_url' => ['https://example.org']]))) === null);
$resource = ResourceHelper::fromParams(new Registry(array_replace($params, ['resource_title' => '<script>alert(1)</script>', 'resource_description' => '<img src=x onerror=alert(2)>'])));
ob_start();
require __DIR__ . '/../extensions/mod_nicoderesources/tmpl/default.php';
$html = ob_get_clean();
$check('text_escaped', str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>') && !str_contains($html, '<img'));
$check('attribute_escaped', str_contains($html, '?a=1&amp;b=2'));
$resource = null;
ob_start();
require __DIR__ . '/../extensions/mod_nicoderesources/tmpl/default.php';
$check('empty_configuration_no_markup', ob_get_clean() === '');
echo json_encode(['passed' => count($checks), 'checks' => $checks], JSON_PRETTY_PRINT) . PHP_EOL;
