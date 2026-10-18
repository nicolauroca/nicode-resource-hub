<?php

namespace Nicode\Plugin\Content\Reading\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Event\Content\AfterTitleEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Nicode\Plugin\Content\Reading\Helper\ReadingTime;

final class Reading extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onContentAfterTitle' => 'showEstimate'];
    }

    public function showEstimate(AfterTitleEvent $event): void
    {
        if (!$this->getApplication()->isClient('site') || $event->getContext() !== 'com_content.article') {
            return;
        }

        $article = $event->getItem();
        $intro = $article->introtext ?? '';
        $full = $article->fulltext ?? '';

        if (!is_string($intro) || !is_string($full)) {
            return;
        }

        $minutes = ReadingTime::minutes($intro . ' ' . $full);

        if ($minutes === 0) {
            return;
        }

        $this->loadLanguage();
        $label = Text::sprintf('PLG_CONTENT_NICODEREADING_ESTIMATE', $minutes);
        $event->addResult('<p class="nicode-reading">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>');
    }
}
