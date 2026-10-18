<?php

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Nicode\Plugin\Content\Reading\Extension\Reading;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(Reading::class, function (Container $container) {
                $plugin = new Reading((array) PluginHelper::getPlugin('content', 'nicodereading'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};
