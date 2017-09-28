<?php

namespace Kutny\CronBundle;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class CronCommandsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $containerBuilder)
    {
        $cronCommandServices = array_keys($containerBuilder->findTaggedServiceIds('cron.command'));

        /** @var Definition $cronCommandManagerDefinition */
        $cronCommandManagerDefinition = $containerBuilder->getDefinition(CronCommandManager::class);
        $cronCommandManagerDefinition->addMethodCall('setCronCommandServices', [$cronCommandServices]);
    }
}
