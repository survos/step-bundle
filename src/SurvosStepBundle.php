<?php declare(strict_types=1);

namespace Survos\StepBundle;

use Survos\StepBundle\Controller\CastorController;
use Survos\StepBundle\Service\CastorStepExporter;
use Survos\StepBundle\Twig\StepRuntimeExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SurvosStepBundle extends AbstractBundle
{
    protected string $extensionAlias = 'survos_step';

    /**
     * Keep this bundle self-contained: register the controller + service here.
     *
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Service that finds castor files and exports steps JSON
        $builder->autowire(CastorStepExporter::class)
            ->setPublic(true)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArgument('$projectDir', '%kernel.project_dir%');

        // Controller
        $builder->autowire(CastorController::class)
            ->setPublic(true)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('controller.service_arguments')
            ->addTag('controller.service_subscriber');

        // inside SurvosStepBundle::loadExtension()
        $builder->autowire(StepRuntimeExtension::class)
            ->addTag('twig.extension');

    }

    public function configure(DefinitionConfigurator $definition): void
    {
        // Optional: future config here (e.g., search paths or depth)
        $definition->rootNode()
            ->children()
                ->scalarNode('search_glob')->defaultValue('*.castor.php')->end()
                ->integerNode('search_depth')->defaultValue(0)->end()
            ->end()
        ;
    }
}
