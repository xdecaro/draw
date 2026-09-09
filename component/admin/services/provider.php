<?php
namespace xdecaro\Component\Draw\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use xdecaro\Component\Draw\Administrator\Extension\DrawComponent;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('xdecaro\\Component\\Draw'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('xdecaro\\Component\\Draw'));
        $container->share(CoreIntegrationService::class, static fn (): CoreIntegrationService => new CoreIntegrationService());
        $container->share(DrawReadService::class, static fn (Container $container): DrawReadService => new DrawReadService($container->get(DatabaseInterface::class)));
        $container->share(DrawService::class, static fn (Container $container): DrawService => new DrawService($container->get(DatabaseInterface::class), $container->get(DrawReadService::class)));
        $container->set(ComponentInterface::class, static function (Container $container): ComponentInterface {
            $component = new DrawComponent(
                $container->get(ComponentDispatcherFactoryInterface::class),
                $container->get(MVCFactoryInterface::class)
            );
            $component->setDrawService($container->get(DrawService::class));
            $component->setReadService($container->get(DrawReadService::class));
            $component->setCoreIntegrationService($container->get(CoreIntegrationService::class));

            return $component;
        });
    }
};
