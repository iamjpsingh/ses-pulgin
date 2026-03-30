<?php

declare(strict_types=1);

use Xtrusio\CoreBundle\DependencyInjection\XtrusioCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $services->load('XtrusioPlugin\\XtrusioAmazonSesBundle\\', '../')
        ->exclude('../{'.implode(',', XtrusioCoreExtension::DEFAULT_EXCLUDES).'}');
};
