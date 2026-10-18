<?php

/** @license GPL-2.0-or-later */

use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Nicode\Lesson\Services\ResourceLabel;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->share(
            ResourceLabel::class,
            static function (Container $container): ResourceLabel {
                return new ResourceLabel($container->get('resource.prefix'));
            }
        );
    }
};
