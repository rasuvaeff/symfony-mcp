<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use Rasuvaeff\SymfonyMcp\RasuvaeffMcpBundle;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * A real Symfony kernel with this bundle, on a throwaway project directory.
 */
final class SymfonyApplication extends Kernel
{
    use MicroKernelTrait;

    private readonly string $projectDir;

    /**
     * @param array<string, mixed> $config
     * @param list<class-string> $services registered by the application
     */
    public function __construct(private readonly array $config = [], private readonly array $services = [])
    {
        $this->projectDir = sys_get_temp_dir() . '/symfony-mcp-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir);
        parent::__construct('test', debug: false);
    }

    /**
     * @param array<string, mixed> $config
     * @param list<class-string> $services
     */
    public static function start(array $config = [], array $services = []): self
    {
        $kernel = new self($config, $services);
        $kernel->boot();

        return $kernel;
    }

    #[\Override]
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new RasuvaeffMcpBundle()];
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function psr(ServerRequestInterface $request): ResponseInterface
    {
        $factory = new Psr17Factory();

        return (new PsrHttpFactory($factory, $factory, $factory, $factory))->createResponse($this->http($request));
    }

    public function http(ServerRequestInterface $request): Response
    {
        $symfonyRequest = (new HttpFoundationFactory())->createRequest($request);
        $response = $this->handle($symfonyRequest);
        $this->terminate($symfonyRequest, $response);

        return $response;
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'http_method_override' => false, 'handle_all_throwables' => true, 'php_errors' => ['log' => false, 'throw' => false], 'test' => true]);
        $container->extension('rasuvaeff_mcp', array_replace_recursive(['session' => ['dir' => $this->projectDir . '/sessions']], $this->config));
        $services = $container->services()->defaults()->autowire()->autoconfigure();

        // the default logger writes to STDERR, which aborts a mutation run
        $services->set('logger', NullLogger::class);

        foreach ($this->services as $service) {
            $services->set($service);
        }
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@RasuvaeffMcpBundle/config/routes.php');
    }
}
