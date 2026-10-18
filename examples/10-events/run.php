<?php

// Keep this exercise outside the web root. Run only against a disposable lab.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$site = realpath($argv[1] ?? '');
$source = realpath($argv[2] ?? '');
if ($argc !== 3 || !$site || !$source
    || !is_file($site . '/configuration.php')
    || !is_file($source . '/src/Extension/Reading.php')) {
    fwrite(STDERR, "Usage: php run.php JOOMLA_SITE PLUGIN_SOURCE\n");
    exit(2);
}

define('_JEXEC', 1);
define('JPATH_BASE', $site);
// A CLI process has no incoming HTTP request. Supply a synthetic local origin
// so SiteApplication initialisation never depends on the invoking shell.
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';
require $source . '/src/Helper/ReadingTime.php';
require $source . '/src/Extension/Reading.php';

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Event\Content\AfterTitleEvent;
use Joomla\CMS\Factory;
use Joomla\Event\Dispatcher;
use Joomla\Registry\Registry;
use Joomla\Session\SessionInterface;
use Nicode\Plugin\Content\Reading\Extension\Reading;
use Nicode\Plugin\Content\Reading\Helper\ReadingTime;

$checks = [];
function check(string $name, bool $passed): void
{
    global $checks;
    $checks[] = ['name' => $name, 'passed' => $passed];
    if (!$passed) {
        throw new RuntimeException($name);
    }
}

function dispatchCase(Reading $plugin, string $context, object $article, array $prior = []): array
{
    $event = new AfterTitleEvent('onContentAfterTitle', [
        'context' => $context,
        'subject' => $article,
        'params' => new Registry(),
        'page' => 0,
    ]);
    foreach ($prior as $result) {
        $event->addResult($result);
    }
    $dispatcher = new Dispatcher();
    $dispatcher->addSubscriber($plugin);
    $dispatcher->dispatch('onContentAfterTitle', $event);
    return $event->getArgument('result', []);
}

try {
    $container = Factory::getContainer();
    $container->alias(SessionInterface::class, 'session.web.site');
    $app = $container->get(SiteApplication::class);
    Factory::$application = $app;
    // Test-only bootstrap: no HTTP request is rendered. This protected method
    // is version-sensitive and is not an extension API to use in production.
    (new ReflectionMethod($app, 'initialiseApp'))->invoke($app);
    check('english_fixture_loaded', $app->getLanguage()->load(
        'plg_content_nicodereading', $source, 'en-GB', true, false
    ));
    $plugin = new Reading(['name' => 'nicodereading', 'type' => 'content']);
    $plugin->setApplication($app);

    foreach ([0, 1, 200, 201, 400, 401] as $words) {
        check('boundary_' . $words, ReadingTime::minutes(str_repeat('resource ', $words)) === (int) ceil($words / 200));
    }
    check('unicode_blocks', ReadingTime::minutes(str_repeat('<p>áéñ</p>', 201)) === 2);
    check('script_style_excluded', ReadingTime::minutes('<script>hidden</script><style>hidden</style>') === 0);
    $article = (object) ['introtext' => 'resource', 'fulltext' => str_repeat('resource ', 200)];
    $before = clone $article;
    $expected = '<p class="nicode-reading">Estimated reading time: 2 min (200 words/min).</p>';
    check('typed_dispatch', dispatchCase($plugin, 'com_content.article', $article) === [$expected]);
    check('article_unchanged', $article == $before);
    check('prior_result_preserved', dispatchCase($plugin, 'com_content.article', $article, ['other']) === ['other', $expected]);
    foreach (['com_content.featured', 'com_content.category', 'com_contact.contact', 'com_users.user'] as $context) {
        check('skip_' . $context, dispatchCase($plugin, $context, $article) === []);
    }
    check('missing_fields', dispatchCase($plugin, 'com_content.article', (object) []) === []);
    check('malformed_fields', dispatchCase($plugin, 'com_content.article', (object) ['introtext' => ['bad']]) === []);
    check('empty_text', dispatchCase($plugin, 'com_content.article', (object) ['introtext' => '<p> </p>']) === []);
    $plugin->setApplication($container->get(AdministratorApplication::class));
    check('administrator_skipped', dispatchCase($plugin, 'com_content.article', $article) === []);
    $plugin->setApplication($app);
    // A second dispatch is a second rendering contribution, not a deduplicated job.
    check('independent_second_dispatch', dispatchCase($plugin, 'com_content.article', $article) === [$expected]);
    echo json_encode(['passed' => true, 'checks' => $checks], JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $error) {
    // Do not expose configuration, SQL or connection details in a test report.
    fwrite(STDERR, "Event exercise failed; inspect the local laboratory privately.\n");
    echo json_encode(['passed' => false, 'checks' => $checks], JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
