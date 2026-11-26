<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp;

use Mcp\Server\Fiber\FiberContextInterface;
use Mcp\Server\Resource\SubscriptionManagerInterface;
use Mcp\Server\Server;
use Mcp\Server\Session\SessionStoreInterface;
use Mcp\Server\Subscription\NotificationBusInterface;
use Mcp\Server\Transport\Http\OAuth\ProtectedResourceMetadataHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Rasuvaeff\Mcp\Configuration\ServerComponentResolver;
use Rasuvaeff\Mcp\Configuration\ServerOptions;
use Rasuvaeff\Mcp\McpAction;
use Rasuvaeff\Mcp\McpServerAssembler;
use Rasuvaeff\Mcp\OAuth\OAuthComponentFactory;
use Rasuvaeff\Mcp\OpenApi\OpenApiConfiguratorResolver;
use Rasuvaeff\Mcp\OpenApi\OpenApiServerConfigurator;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Builds the server from the bundle options. Everything is built on first use,
 * never while the container is compiled, so `%env()%` values are read at run
 * time. What serves one request is dropped by `reset()` between requests of a
 * worker; sessions on disk and the notification bus stay.
 *
 * @api
 */
final class McpRuntime implements ResetInterface
{
    private ?ServerOptions $options = null;

    private ?Server $server = null;

    /**
     * @param ContainerInterface $container locator of the classes named in the options
     * @param array<string, mixed> $params
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $params,
        private readonly SubscriptionManagerInterface $subscriptionManager,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?FiberContextInterface $fiberContext = null,
    ) {}

    public function options(): ServerOptions
    {
        return $this->options ??= ServerOptions::fromParams($this->params);
    }

    public function sessionStore(): SessionStoreInterface
    {
        /** @var SessionStoreInterface */
        return $this->container->get(SessionStoreInterface::class);
    }

    public function notificationBus(): ?NotificationBusInterface
    {
        if (!$this->container->has(NotificationBusInterface::class)) {
            return null;
        }

        /** @var NotificationBusInterface */
        return $this->container->get(NotificationBusInterface::class);
    }

    public function server(): Server
    {
        if ($this->server instanceof Server) {
            return $this->server;
        }

        /** @var array<string, mixed> $openapi */
        $openapi = $this->params['openapi'] ?? [];
        $bridge = (new OpenApiConfiguratorResolver($this->container, $openapi))->resolve();

        return $this->server = (new McpServerAssembler(
            factory: $this->options()->serverFactory(
                container: $this->container,
                sessionStore: $this->sessionStore(),
                logger: $this->logger,
                subscriptionManager: $this->subscriptionManager,
                notificationBus: $this->notificationBus(),
                fiberContext: $this->fiberContext,
            ),
            components: (new ServerComponentResolver($this->container, $this->params))->resolve($bridge instanceof OpenApiServerConfigurator ? [$bridge] : []),
        ))->create();
    }

    public function action(): McpAction
    {
        $factory = new Psr17Factory();

        return new McpAction(
            server: $this->server(),
            responseFactory: $factory,
            streamFactory: $factory,
            allowedHosts: $this->options()->allowedHosts(),
            sessionStore: $this->sessionStore(),
        );
    }

    public function guard(): MiddlewareInterface
    {
        return match (Guard::fromConfig($this->params['guard'] ?? Guard::Secret->value)) {
            Guard::Secret => $this->options()->sharedSecretMiddleware(new Psr17Factory()),
            Guard::OAuth => $this->oauth()->bearerTokenMiddleware(),
        };
    }

    public function oauthMetadataHandler(): ProtectedResourceMetadataHandler
    {
        return $this->oauth()->metadataHandler();
    }

    #[\Override]
    public function reset(): void
    {
        $this->server = null;
    }

    private function oauth(): OAuthComponentFactory
    {
        /** @var array<string, mixed> $oauth */
        $oauth = $this->params['oauth'] ?? [];

        return new OAuthComponentFactory(params: $oauth, container: $this->container);
    }
}
