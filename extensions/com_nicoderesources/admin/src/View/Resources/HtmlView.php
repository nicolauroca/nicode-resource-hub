<?php

namespace Nicode\Component\Resources\Administrator\View\Resources;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $items = [];

    public function display($tpl = null)
    {
        $this->items = $this->getModel()->getItems();

        return parent::display($tpl);
    }
}
