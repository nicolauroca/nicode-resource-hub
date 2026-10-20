<?php

/** @license GPL-2.0-or-later */

\defined('_JEXEC') or die;

if ($resources === []) {
    return;
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<ul class="mod-nicoderesources mod-nicoderesources--compact">
    <?php foreach ($resources as $resource) : ?>
        <li><a href="<?php echo $escape($resource['url']); ?>"><?php echo $escape($resource['title']); ?></a></li>
    <?php endforeach; ?>
</ul>
