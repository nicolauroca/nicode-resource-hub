<?php

/** @license GPL-2.0-or-later */

\defined('_JEXEC') or die;

if ($resources === []) {
    return;
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="mod-nicoderesources">
    <ul>
    <?php foreach ($resources as $resource) : ?>
        <li>
    <p class="mod-nicoderesources__title">
        <a href="<?php echo $escape($resource['url']); ?>"><?php echo $escape($resource['title']); ?></a>
    </p>
    <?php if ($resource['description'] !== '') : ?>
        <p><?php echo $escape($resource['description']); ?></p>
    <?php endif; ?>
        </li>
    <?php endforeach; ?>
    </ul>
</div>
