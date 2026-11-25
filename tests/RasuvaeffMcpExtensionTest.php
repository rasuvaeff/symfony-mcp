<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp\Tests;

use Mcp\Server\Resource\SubscriptionManagerInterface;
use Mcp\Server\Server;
use Mcp\Server\Session\SessionStoreInterface;
use Mcp\Server\Subscription\InMemoryNotificationBus;
use Mcp\Server\Subscription\NotificationBusInterface;
use Mcp\Server\Subscription\Psr16NotificationBus;
use Mcp\Server\Subscription\Psr6NotificationBus;
use Mcp\Server\Transport\Http\OAuth\ProtectedResourceMetadataHandler;
use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Mcp\Resource\ResourceUpdateNotifier;
use Rasuvaeff\SymfonyMcp\Configuration;
use Rasuvaeff\SymfonyMcp\Guard;
use Rasuvaeff\SymfonyMcp\McpController;
use Rasuvaeff\SymfonyMcp\McpRuntime;
use Rasuvaeff\SymfonyMcp\RasuvaeffMcpBundle;
use Rasuvaeff\SymfonyMcp\RasuvaeffMcpExtension;
use Rasuvaeff\SymfonyMcp\ServiceClasses;
use Rasuvaeff\SymfonyMcp\Tests\Support\ClockTool;
use Rasuvaeff\SymfonyMcp\Tests\Support\FixedInterceptor;
use Rasuvaeff\SymfonyMcp\Tests\Support\NotFoundHandler;
use Rasuvaeff\SymfonyMcp\Tests\Support\NotifyingService;
use Rasuvaeff\SymfonyMcp\Tests\Support\SymfonyApplication;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(RasuvaeffMcpBundle::class)]
#[Covers(RasuvaeffMcpExtension::class)]
#[Covers(Configuration::class)]
#[Covers(ServiceClasses::class)]
#[Covers(McpRuntime::class)]
#[Covers(McpController::class)]
#[Covers(Guard::class)]
final class RasuvaeffMcpExtensionTest
{
    public function theDefaultConfigurationRefusesEveryRequest(): void
    {
        Assert::same(SymfonyApplication::start()->psr(new ServerRequest('POST', 'http://localhost/mcp'))->getStatusCode(), 503);
    }

    public function theRouteServesTheProtocolMethodsOnly(): void
    {
        $route = $this->start()->getContainer()->get('router')->getRouteCollection()->get('rasuvaeff_mcp');

        Assert::same($route->getPath(), '/mcp');
        Assert::same($route->getMethods(), ['GET', 'POST', 'DELETE', 'OPTIONS']);
    }

    public function environmentValuesAreReadAtRunTime(): void
    {
        $_ENV['MCP_TEST_SECRET'] = 'first';
        $application = $this->start(['endpoint_secret' => '%env(MCP_TEST_SECRET)%']);

        try {
            Assert::same($this->initialize($application, 'first')->getStatusCode(), 200);
            Assert::same($this->initialize($application, 'other')->getStatusCode(), 401);
        } finally {
            unset($_ENV['MCP_TEST_SECRET']);
        }
    }

    public function keysNamedByTheApplicationAreKept(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            ['cache' => ['tools' => ['order-status' => 10]], 'client_secrets' => ['ci-bot' => 's']],
            ['server_name' => 'app'],
        ]);

        Assert::same($config, ['cache' => ['tools' => ['order-status' => 10]], 'client_secrets' => ['ci-bot' => 's'], 'server_name' => 'app']);
    }

    public function aLaterFileReplacesAKey(): void
    {
        Assert::same(
            (new Processor())->processConfiguration(new Configuration(), [['tools' => ['A', 'B']], ['tools' => ['C']]]),
            ['tools' => ['C']],
        );
    }

    public function everyNamedServiceIsCollected(): void
    {
        Assert::same(ServiceClasses::fromParams([
            'tools' => ['T1', 'T2'],
            'interceptors' => ['I'],
            'prompt_interceptors' => ['P'],
            'resource_interceptors' => ['R'],
            'result_decorators' => ['D'],
            'configurators' => ['C', 'T1'],
            'tool_visibility' => 'TV',
            'prompt_visibility' => 'PV',
            'resource_visibility' => 'RV',
            'openapi' => ['operation_modifier' => 'OM', 'identity_provider' => 'IP', 'delegated_header_provider' => 'DH', 'handler' => 'H', 'request_attributes' => 'RA', 'in_process_scope' => 'S', 'base_url' => 'no'],
            'oauth' => ['validator' => 'V', 'cache' => '', 'resource' => 'no'],
            'server_name' => 'no',
        ]), ['T1', 'T2', 'I', 'P', 'R', 'D', 'C', 'TV', 'PV', 'RV', 'OM', 'IP', 'DH', 'H', 'RA', 'S', 'V']);
        Assert::same(ServiceClasses::fromParams([]), []);
    }

    public function aRegisteredServiceIsNotReplaced(): void
    {
        $container = new ContainerBuilder();
        $own = $container->register(ClockTool::class, ClockTool::class)->setPublic(boolean: true);
        (new RasuvaeffMcpExtension())->load([['tools' => [ClockTool::class]]], $container);

        Assert::same($container->getDefinition(ClockTool::class), $own);
    }

    public function anUnregisteredClassIsAutowiredAndPrivate(): void
    {
        $container = new ContainerBuilder();
        (new RasuvaeffMcpExtension())->load([['tools' => [ClockTool::class, 'nothing']]], $container);
        $definition = $container->getDefinition(ClockTool::class);

        Assert::true($definition->isAutowired());
        Assert::true($definition->isAutoconfigured());
        Assert::false($definition->isPublic());
        Assert::false($container->has('nothing'));
        Assert::same((new RasuvaeffMcpBundle())->getContainerExtension()->getAlias(), 'rasuvaeff_mcp');
    }

    public function resetDropsTheServerAndKeepsTheRest(): void
    {
        $runtime = $this->runtime($this->start(['notifications' => ['bus' => 'memory']]));
        $server = $runtime->server();
        $store = $runtime->sessionStore();
        $bus = $runtime->notificationBus();
        $options = $runtime->options();

        Assert::same($runtime->server(), $server);
        $runtime->reset();

        Assert::notSame($runtime->server(), $server);
        Assert::same($runtime->sessionStore(), $store);
        Assert::same($runtime->notificationBus(), $bus);
        Assert::same($runtime->options(), $options);
    }

    public function theKernelResetsTheRuntimeBetweenRequests(): void
    {
        $application = $this->start();
        $runtime = $this->runtime($application);
        $server = $runtime->server();
        $application->getContainer()->get('services_resetter')->reset();

        Assert::notSame($runtime->server(), $server);
    }

    /**
     * @param class-string|null $expected
     */
    #[DataProvider('busProvider')]
    public function theNotificationBusFollowsTheOption(string $kind, ?string $expected): void
    {
        $container = new ContainerBuilder();
        (new RasuvaeffMcpExtension())->load([['notifications' => ['bus' => $kind]]], $container);
        Assert::same($container->has(NotificationBusInterface::class), $expected !== null);

        $bus = $this->runtime($this->start(['notifications' => ['bus' => $kind]]))->notificationBus();

        if ($expected === null) {
            Assert::null($bus);
        } else {
            Assert::instanceOf($bus, $expected);
        }
    }

    public static function busProvider(): iterable
    {
        yield 'none' => ['', null];
        yield 'memory' => ['memory', InMemoryNotificationBus::class];
        yield 'psr16' => ['psr16', Psr16NotificationBus::class];
        yield 'psr6' => ['psr6', Psr6NotificationBus::class];
    }

    public function theNotifierPublishesToTheBusOfTheServer(): void
    {
        foreach (['memory' => true, '' => false] as $kind => $hasBus) {
            $application = SymfonyApplication::start(['notifications' => ['bus' => $kind]], [NotifyingService::class]);
            $container = $application->getContainer()->get('test.service_container');

            Assert::equals($container->get(NotifyingService::class)->notifier, new ResourceUpdateNotifier($container->get(SubscriptionManagerInterface::class), $hasBus ? $this->runtime($application)->notificationBus() : null));
        }
    }

    public function theServicesComeFromTheRuntime(): void
    {
        $application = $this->start(['oauth' => ['resource' => 'https://mcp.test/mcp', 'authorization_servers' => ['https://id.test']], 'notifications' => ['bus' => 'memory']]);
        $runtime = $this->runtime($application);
        $container = new ContainerBuilder();
        (new RasuvaeffMcpExtension())->load([['notifications' => ['bus' => 'memory']]], $container);

        foreach ([Server::class => 'server', SessionStoreInterface::class => 'sessionStore', ProtectedResourceMetadataHandler::class => 'oauthMetadataHandler', NotificationBusInterface::class => 'notificationBus'] as $id => $method) {
            $factory = $container->getDefinition($id)->getFactory();
            Assert::same((string) $factory[0], McpRuntime::class);
            Assert::same($factory[1], $method);
        }

        Assert::false($container->getDefinition(Server::class)->isShared());
        Assert::true($container->getDefinition(NotificationBusInterface::class)->isShared());
        Assert::instanceOf($runtime->oauthMetadataHandler(), ProtectedResourceMetadataHandler::class);
    }

    public function onlyAllowedHostsAreServed(): void
    {
        $application = $this->start(['allowed_hosts' => ['mcp.test']]);

        Assert::same($this->initialize($application, 'secret', 'mcp.test')->getStatusCode(), 200);
        Assert::same($this->initialize($application, 'secret', 'evil.test')->getStatusCode(), 403);
    }

    public function theOAuthGuardNeedsItsOptions(): void
    {
        $caught = null;

        try {
            $this->runtime($this->start(['guard' => 'oauth']))->guard();
        } catch (\InvalidArgumentException $caught) {
        }

        Assert::notNull($caught);
        Assert::string($caught->getMessage())->contains('OAuth is not configured');
    }

    public function anUnknownGuardFails(): void
    {
        $caught = null;

        try {
            $this->runtime($this->start(['guard' => 'none']))->guard();
        } catch (\ValueError $caught) {
        }

        Assert::notNull($caught);
    }

    public function theConsoleCommandsAreRegistered(): void
    {
        $application = new Application($this->start());
        $application->setAutoExit(boolean: false);
        $tester = new ApplicationTester($application);

        Assert::same($tester->run(['command' => 'mcp:list']), 0);
        Assert::string($tester->getDisplay())->contains('clock');
        Assert::true($application->has('mcp:serve'));
    }

    public function configuredInterceptorsRunOnEveryCall(): void
    {
        $application = $this->start(['interceptors' => [FixedInterceptor::class]]);
        $response = $application->psr(new ServerRequest('POST', 'http://localhost/mcp', $this->headers('secret') + [
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => 'tools/call',
            'Mcp-Name' => 'clock',
        ], '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"clock","arguments":{},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}'));

        Assert::string((string) $response->getBody())->contains('intercepted');
    }

    public function openApiOperationsBecomeTools(): void
    {
        $spec = tempnam(sys_get_temp_dir(), prefix: 'spec');
        file_put_contents($spec, json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'T', 'version' => '1'],
            'paths' => ['/items' => ['get' => ['operationId' => 'listItems', 'responses' => ['200' => ['description' => 'ok']]]]],
        ], JSON_THROW_ON_ERROR));
        $application = new Application($this->start(['openapi' => ['spec_path' => $spec, 'base_url' => 'https://api.test', 'operations' => ['listItems'], 'executor' => 'psr15', 'handler' => NotFoundHandler::class]]));
        $application->setAutoExit(boolean: false);
        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'mcp:list']);
        unlink($spec);

        Assert::string($tester->getDisplay())->contains('listItems');
    }

    public function aListenStreamIsNotBuffered(): void
    {
        $status = $this->start()->http(new ServerRequest('POST', 'http://localhost/mcp', $this->headers('secret') + [
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => 'subscriptions/listen',
        ], '{"jsonrpc":"2.0","id":"l","method":"subscriptions/listen","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}},"notifications":{"toolsListChanged":true}}}'));

        Assert::instanceOf($status, StreamedResponse::class);
        Assert::false($this->start()->http(new ServerRequest('POST', 'http://localhost/mcp')) instanceof StreamedResponse);
    }

    private function initialize(SymfonyApplication $application, string $secret, string $host = 'localhost'): \Psr\Http\Message\ResponseInterface
    {
        return $application->psr(new ServerRequest('POST', 'http://' . $host . '/mcp', $this->headers($secret) + ['Host' => $host], '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"t","version":"1"}}}'));
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $secret): array
    {
        return ['X-Mcp-Secret' => $secret, 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream'];
    }

    private function runtime(SymfonyApplication $application): McpRuntime
    {
        return $application->getContainer()->get('test.service_container')->get(McpRuntime::class);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function start(array $config = []): SymfonyApplication
    {
        return SymfonyApplication::start($config + ['tools' => [ClockTool::class], 'endpoint_secret' => 'secret']);
    }
}
