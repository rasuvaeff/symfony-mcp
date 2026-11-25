<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp;

use Mcp\Server\Fiber\FiberContextInterface;
use Mcp\Server\Resource\SessionSubscriptionManager;
use Mcp\Server\Resource\SubscriptionManagerInterface;
use Mcp\Server\Server;
use Mcp\Server\Session\SessionStoreInterface;
use Mcp\Server\Subscription\NotificationBusInterface;
use Mcp\Server\Transport\Http\OAuth\ProtectedResourceMetadataHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\Mcp\Configuration\ServerOptions;
use Rasuvaeff\Mcp\McpListCommand;
use Rasuvaeff\Mcp\McpServeCommand;
use Rasuvaeff\Mcp\Resource\ResourceUpdateNotifier;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the services from the `rasuvaeff_mcp` options.
 *
 * @internal
 */
final class RasuvaeffMcpExtension extends Extension
{
    public const string ALIAS = 'rasuvaeff_mcp';

    #[\Override]
    public function getAlias(): string
    {
        return self::ALIAS;
    }

    /**
     * @param array<array-key, mixed> $configs
     */
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array<string, mixed> $config */
        $config = $this->processConfiguration(new Configuration(), $configs);
        $references = [];

        foreach (ServiceClasses::fromParams($config) as $class) {
            // a class the application did not register is registered here, autowired and private
            if (class_exists($class) && !$container->has($class)) {
                $container->register($class, $class)->setAutowired(autowired: true)->setAutoconfigured(autoconfigured: true);
            }

            $references[$class] = new Reference($class);
        }

        foreach ([RequestFactoryInterface::class, ResponseFactoryInterface::class, ServerRequestFactoryInterface::class, StreamFactoryInterface::class] as $factory) {
            $id = 'rasuvaeff_mcp.psr17_factory';
            $container->setDefinition($id, new Definition(Psr17Factory::class));
            $references[$factory] = new Reference($id);
        }

        $container->setDefinition('rasuvaeff_mcp.cache', (new Definition(Psr16Cache::class))->setArguments([new Reference(CacheItemPoolInterface::class)]));
        $references[CacheInterface::class] = new Reference('rasuvaeff_mcp.cache', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
        $references[CacheItemPoolInterface::class] = new Reference(CacheItemPoolInterface::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
        $references[ClientInterface::class] = new Reference(ClientInterface::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
        $references[LoggerInterface::class] = new Reference(LoggerInterface::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE);

        // a service of the application with one of these ids wins
        $references[SessionStoreInterface::class] = new Reference(SessionStoreInterface::class);
        $references[NotificationBusInterface::class] = new Reference(NotificationBusInterface::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
        $locator = ServiceLocatorTagPass::register($container, $references);
        $container->setDefinition(ServerOptions::class, (new Definition(ServerOptions::class))->setFactory([ServerOptions::class, 'fromParams'])->setArguments([$config]));
        $container->setDefinition(SessionStoreInterface::class, (new Definition(SessionStoreInterface::class))->setFactory([new Reference(ServerOptions::class), 'sessionStore']));

        $container->setDefinition(SessionSubscriptionManager::class, new Definition(SessionSubscriptionManager::class));
        $container->setAlias(SubscriptionManagerInterface::class, SessionSubscriptionManager::class);
        $container->setDefinition(McpRuntime::class, (new Definition(McpRuntime::class))
            ->setArguments([
                $locator,
                $config,
                new Reference(SubscriptionManagerInterface::class),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(FiberContextInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('kernel.reset', ['method' => 'reset']));

        // not shared: they follow the runtime, which a worker resets between requests
        foreach ([Server::class => 'server', ProtectedResourceMetadataHandler::class => 'oauthMetadataHandler'] as $id => $method) {
            $container->setDefinition($id, (new Definition($id))->setFactory([new Reference(McpRuntime::class), $method])->setShared(shared: false));
        }

        // an application without a bus has no such service: ResourceUpdateNotifier takes it as optional
        if (($config['notifications']['bus'] ?? '') !== '') {
            $container->setDefinition(NotificationBusInterface::class, (new Definition(NotificationBusInterface::class))
                ->setFactory([new Reference(ServerOptions::class), 'notificationBus'])
                ->setArguments([$locator]));
        }

        $container->setDefinition(ResourceUpdateNotifier::class, (new Definition(ResourceUpdateNotifier::class))->setArguments([
            new Reference(SubscriptionManagerInterface::class),
            new Reference(NotificationBusInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]));

        $container->setDefinition(McpController::class, (new Definition(McpController::class))
            ->setArguments([new Reference(McpRuntime::class)])
            ->addTag('controller.service_arguments'));
        $factory = new Reference('rasuvaeff_mcp.psr17_factory');
        $container->setDefinition(McpServeCommand::class, (new Definition(McpServeCommand::class))
            ->setArguments([new Reference(Server::class)])
            ->addTag('console.command'));
        $container->setDefinition(McpListCommand::class, (new Definition(McpListCommand::class))
            ->setArguments([new Reference(Server::class), $factory, $factory, $factory])
            ->addTag('console.command'));
    }
}
