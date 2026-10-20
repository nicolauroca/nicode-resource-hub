<?php

/** @license GPL-2.0-or-later */

namespace Nicode\Module\Resources\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Nicode\Module\Resources\Site\Helper\ResourceHelper;

\defined('_JEXEC') or die;

final class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();
        $data['resources'] = ResourceHelper::listFromParams($data['params']);

        return $data;
    }
}
