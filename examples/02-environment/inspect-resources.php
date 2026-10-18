<?php

/** @license GPL-2.0-or-later */

declare(strict_types=1);

if (!in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
    http_response_code(404);
    exit;
}

$path = $argv[1] ?? '';
if ($argc !== 2 || !is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "Usage: php inspect-resources.php /path/to/resources.json\n");
    exit(2);
}

try {
    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException('Could not read the fixture.');
    }
    $resources = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
    if (!is_array($resources)) {
        throw new RuntimeException('Expected a JSON array of resource objects.');
    }
    $seen = [];
    $published = 0;
    foreach ($resources as $offset => $resource) {
        $row = $offset + 1;
        if (!$resource instanceof stdClass) {
            throw new RuntimeException("Row $row: expected an object.");
        }
        $keys = array_keys(get_object_vars($resource));
        sort($keys);
        if ($keys !== ['id', 'published', 'title', 'url']) {
            throw new RuntimeException("Row $row: expected exactly id, title, url, published.");
        }
        if (!is_int($resource->id) || $resource->id < 1) {
            throw new RuntimeException("Row $row: id must be a positive integer.");
        }
        if (isset($seen[$resource->id])) {
            throw new RuntimeException("Row $row: duplicate id {$resource->id}.");
        }
        $seen[$resource->id] = true;
        if (!is_string($resource->title) || trim($resource->title) === '') {
            throw new RuntimeException("Row $row: title must be a nonempty string.");
        }
        if (
            !is_string($resource->url)
            || filter_var($resource->url, FILTER_VALIDATE_URL) === false
            || parse_url($resource->url, PHP_URL_SCHEME) !== 'https'
        ) {
            throw new RuntimeException("Row $row: url must be an absolute HTTPS URL.");
        }
        if (!is_bool($resource->published)) {
            throw new RuntimeException("Row $row: published must be a JSON boolean.");
        }
        $published += (int) $resource->published;
    }
    $total = count($resources);
    $drafts = $total - $published;
    echo "PASS resources=$total published=$published drafts=$drafts\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
