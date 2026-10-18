<?php

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

?>
<section class="nicode-resources" aria-labelledby="nicode-resources-title">
    <h1 id="nicode-resources-title"><?php echo $this->escape(Text::_('COM_NICODERESOURCES_HEADING')); ?></h1>
    <p><?php echo $this->escape(Text::_('COM_NICODERESOURCES_INTRO')); ?></p>
    <ul>
        <?php foreach ($this->items as $item) : ?>
            <li>
                <h2><?php echo $this->escape($item->title); ?></h2>
                <p><?php echo $this->escape($item->summary); ?></p>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
