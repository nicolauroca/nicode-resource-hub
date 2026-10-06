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
$resources = $resource === null ? [] : [$resource];
ob_start();
require __DIR__ . '/../extensions/mod_nicoderesources/tmpl/default.php';
$html = ob_get_clean();
$check('text_escaped', str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>') && !str_contains($html, '<img'));
$check('attribute_escaped', str_contains($html, '?a=1&amp;b=2'));
$resource = null;
$resources = $resource === null ? [] : [$resource];
ob_start();
require __DIR__ . '/../extensions/mod_nicoderesources/tmpl/default.php';
$check('empty_configuration_no_markup', ob_get_clean() === '');
$second = ['resource_title' => 'Downloads', 'resource_url' => 'https://downloads.joomla.org/'];
$third = ['resource_title' => 'Community', 'resource_url' => 'https://community.joomla.org/'];
$list = static fn ($rows) => ResourceHelper::listFromParams(new Registry(array_replace($params, ['additional_resources' => $rows])));
$check('legacy_first_preserved', ResourceHelper::listFromParams(new Registry($params)) === [ResourceHelper::fromParams(new Registry($params))]);
$check('three_resources_ordered', array_column($list([$second, $third]), 'title') === ['Joomla manual', 'Downloads', 'Community']);
$check('object_rows_supported', count($list((object) ['row0' => (object) $second])) === 2);
$check('invalid_row_skipped', array_column($list([['resource_url' => 'javascript:bad'], $second]), 'title') === ['Joomla manual', 'Downloads']);
$check('scalar_rows_skipped', count($list([null, 'bad', 12, $second])) === 2);
$check('scalar_container_ignored', count($list('bad')) === 1);
$check('empty_additional_preserves_first', count($list([])) === 1);
$check('bounded_to_ten', count($list(array_fill(0, 50, $second))) === 10);
$check('invalid_rows_count_towards_bound', count($list(array_merge(array_fill(0, 9, null), [$second]))) === 1);
$check('invalid_first_does_not_hide_valid_rows', count(ResourceHelper::listFromParams(new Registry(['additional_resources' => [$second]]))) === 1);
$check('empty_configuration_empty_list', ResourceHelper::listFromParams(new Registry()) === []);
$resources = $list([$second, $third]);
ob_start();
require __DIR__ . '/../extensions/mod_nicoderesources/tmpl/default.php';
$html = ob_get_clean();
$check('semantic_list_three_items', substr_count($html, '<li>') === 3 && substr_count($html, '<ul>') === 1);
$check('list_order_rendered', strpos($html, 'Joomla manual') < strpos($html, 'Downloads') && strpos($html, 'Downloads') < strpos($html, 'Community'));
$resources = $list([array_replace($second, ['resource_title' => '<script>bad</script>', 'resource_description' => '<img src=x>'])]);
ob_start();
require __DIR__ . '/../extensions/mod_nicoderesources/tmpl/default.php';
$html = ob_get_clean();
$check('additional_content_escaped', !str_contains($html, '<script>') && !str_contains($html, '<img') && str_contains($html, '&lt;script&gt;'));
echo json_encode(['passed' => count($checks), 'checks' => $checks], JSON_PRETTY_PRINT) . PHP_EOL;
