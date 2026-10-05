<?php

/** @license GPL-2.0-or-later */

namespace Nicode\Module\Resources\Site\Helper;

use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

final class ResourceHelper
{
    /** Return a display record, or null when the configuration is incomplete or unsafe. */
    public static function fromParams(Registry $params): ?array
    {
        $title = $params->get('resource_title', '');
        $url = $params->get('resource_url', '');
        $description = $params->get('resource_description', '');

        if (!is_string($title) || !is_string($url) || !is_string($description)) {
            return null;
        }

        $title = trim($title);
        $url = trim($url);

        if ($title === '' || preg_match('/[\x00-\x20\x7f]/', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);

        if (
            $parts === false
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        return ['title' => $title, 'url' => $url, 'description' => trim($description)];
    }
}
