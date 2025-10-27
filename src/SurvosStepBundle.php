<?php declare(strict_types=1);

namespace Survos\StepBundle;

use Survos\CoreBundle\Traits\HasAssetMapperTrait;
use Survos\StepBundle\Controller\CastorController;
use Survos\StepBundle\Controller\CastorLogController;
use Survos\StepBundle\Service\CastorLogLocator;
use Survos\StepBundle\Service\CastorStepExporter;
use Survos\StepBundle\Twig\StepRuntimeExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SurvosStepBundle extends AbstractBundle
{
    const string NAME = 'step'; // @todo use this for getPaths()

    use HasAssetMapperTrait; // this creates a dependency on core-bundle!  Maybe just repeat it here?
    protected string $extensionAlias = 'survos_step';

    /**
     * Keep this bundle self-contained: register the controller + service here.
     *
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Service that finds castor files and exports steps JSON
        array_map(fn($class) => $builder->autowire($class)
            ->setPublic(true)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArgument('$projectDir', '%kernel.project_dir%'), [CastorStepExporter::class, CastorLogLocator::class]);

        // Controllers
        array_map(fn(string $class) => $builder->autowire($class)
            ->setPublic(true)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('controller.service_arguments')
            ->addTag('controller.service_subscriber')
        , [CastorController::class, CastorLogController::class]);

        // inside SurvosStepBundle::loadExtension()
        $builder->autowire(StepRuntimeExtension::class)
            ->setArgument('$projectDir', '%kernel.project_dir%')
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

    /**
     * @return array<string>
     */
    public function getPaths(): array
    {
        if ($dir = realpath(__DIR__ . '/../assets/')) {
            if (!file_exists($dir)) {
                throw new \RuntimeException(sprintf('The directory "%s" does not exist.', $dir));
            }
        }
        return [$dir => '@survos/step'];
    }

}
