<?php

namespace Kutny\CronBundle;

use Kutny\CronBundle\CronCommandsCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class KutnyCronBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(
            new CronCommandsCompilerPass(),
            PassConfig::TYPE_BEFORE_REMOVING
        );
    }
}
