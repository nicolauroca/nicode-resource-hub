<?php

namespace Nicode\Component\Resources\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class ResourcesModel extends BaseDatabaseModel
{
    public function getItems(): array
    {
        return [
            (object) ['title' => 'Build & understand <Joomla>', 'summary' => 'Trace one request from controller to template.'],
            (object) ['title' => 'A resource worth sharing', 'summary' => 'Keep the first component small enough to explain.'],
        ];
    }
}
