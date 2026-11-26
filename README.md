# rasuvaeff/symfony-mcp

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/symfony-mcp?label=stable&sort_semver=1)](https://packagist.org/packages/rasuvaeff/symfony-mcp)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/symfony-mcp)](https://packagist.org/packages/rasuvaeff/symfony-mcp)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/symfony-mcp/build.yml?branch=master)](https://github.com/rasuvaeff/symfony-mcp/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/symfony-mcp/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/symfony-mcp/actions)
[![Psalm level](https://img.shields.io/badge/psalm-level%201-141F48?logo=psalm&logoColor=white)](https://github.com/rasuvaeff/symfony-mcp/blob/master/psalm.xml)
[![License](https://img.shields.io/packagist/l/rasuvaeff/symfony-mcp)](LICENSE.md)

[Русская версия](README.ru.md)

Symfony integration of [`rasuvaeff/mcp`](https://github.com/rasuvaeff/mcp), a Model Context
Protocol server over the official [`mcp/sdk`](https://packagist.org/packages/mcp/sdk): tools from
the container, an HTTP endpoint, console commands, and the
[OpenAPI bridge](https://github.com/rasuvaeff/mcp-openapi).

[`symfony/mcp-bundle`](https://github.com/symfony/mcp-bundle) is the official bundle, on the same `mcp/sdk`. Choose it unless you need what this one is for: the same server code in several frameworks, the OpenAPI bridge, or the `rasuvaeff/mcp` interceptors and visibility.

> **Using an AI coding assistant?** [llms.txt](llms.txt) contains a compact API
> reference you can share with the model. Contributors: see [AGENTS.md](AGENTS.md).
> Projects using the [llm/skills](https://github.com/roxblnfk/skills) Composer
> plugin also get this package's agent skill synced into `.agents/skills/`
> automatically on install.

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.3 – 8.6 |
| Symfony | `^7.4 \|\| ^8.0` |
| `rasuvaeff/mcp` | `^0.1.4` |
| `rasuvaeff/mcp-openapi` | `^0.1.3` |
| `firebase/php-jwt` | optional, `^7.0`: JWT validation for OAuth |

## Installation

```bash
composer require rasuvaeff/symfony-mcp
```

```php
// config/bundles.php
Rasuvaeff\SymfonyMcp\RasuvaeffMcpBundle::class => ['all' => true],

// config/routes/rasuvaeff_mcp.php
return static function (RoutingConfigurator $routes): void {
    $routes->import('@RasuvaeffMcpBundle/config/routes.php');
};
```

## Usage

```php
use Mcp\Server\Capability\Attribute\McpTool;

final readonly class OrderTools
{
    public function __construct(private OrderRepository $orders) {}

    #[McpTool(name: 'order.status')]
    public function status(string $orderId): string
    {
        return $this->orders->status($orderId);
    }
}
```

```yaml
# config/packages/rasuvaeff_mcp.yaml
rasuvaeff_mcp:
    server_name: my-app
    tools: ['App\Mcp\OrderTools']
    endpoint_secret: '%env(MCP_SECRET)%'
    allowed_hosts: ['mcp.example.com']
```

The endpoint is `/mcp`. Without a secret it answers 503 to everything.

```bash
bin/console mcp:list    # what is served
bin/console mcp:serve   # stdio, for a local agent
```

### Configuration

`guard` is `secret` or `oauth`. Every other key is the one `rasuvaeff/yii3-mcp` uses; see the
[documentation](https://rasuvaeff.github.io/mcp/). The options are validated when the server is
first built, not when the container is compiled, so `%env()%` works everywhere except in
class names and `notifications.bus`. A later config file replaces a top-level key as a whole.

Serve `ProtectedResourceMetadataHandler` (a service) from a route of your own at
`/.well-known/oauth-protected-resource/<path>`:

```php
#[Route('/.well-known/oauth-protected-resource/mcp', methods: 'GET')]
public function metadata(Request $request, ProtectedResourceMetadataHandler $handler): Response
{
    $factory = new Psr17Factory();
    $psr = (new PsrHttpFactory($factory, $factory, $factory, $factory))->createRequest($request);

    return (new HttpFoundationFactory())->createResponse($handler->handle($psr));
}
```

Code outside MCP announces a change of a resource through the `ResourceUpdateNotifier` service
(needs `notifications.bus`).

### Several nodes

Stateless calls need nothing. For sessions define a shared store; notifications and the call budget
follow the cache of the application, so point it at Redis:

```yaml
# config/services.yaml
services:
    Mcp\Server\Session\SessionStoreInterface:
        class: Mcp\Server\Session\Psr6SessionStore
        arguments: ['@cache.app']
```

The call budget counts over PSR-16, which has no atomic increment: calls sent at the same moment
may all pass. It stops a loop, it is not a quota.

A Symfony cache pool hides the failure of its backend: while Redis is down, `set()` returns `false` instead of throwing. The server reads that as a failed write and rejects the call: a store that is down fails closed instead of lifting the budget.

Keycloak puts your endpoint in `aud` only through an audience mapper on the client; without it
every token is refused with 401.

While the session store is unavailable, `initialize` still answers with a session id: the SDK logs the failed save, and the next request with that id gets 404, after which the client opens a new session.

### Long-running workers

- Checked on FrankenPHP (classic and worker), Swoole and RoadRunner. Under RoadRunner a `subscriptions/listen` stream arrives in one piece when it ends: the PSR-7 bridge captures the whole body. Keep `notifications.subscription_lifetime` short there, or serve listen streams from another runtime.
- `guard` other than `secret` or `oauth`, an empty one included, fails with `Unknown MCP guard`.
- Classes named in the options are registered autowired and private; a service of yours with the same id wins.
- The server is dropped by `kernel.reset` between requests of a worker. Sessions on disk and the notification bus stay.
- A tool object is an ordinary service and lives across the requests of a worker: its properties are seen by the next client. Keep no request state in them, or implement `ResetInterface`.
- Do not `exclude:` a tool class in `services.yaml`: the container does not compile ("reference to an abstract definition").
- The option alias is `rasuvaeff_mcp`: `mcp` belongs to `symfony/mcp-bundle`.

### Components

| Class | Role |
|---|---|
| `RasuvaeffMcpBundle` | the bundle |
| `McpController` | route action: guard, then the MCP endpoint |
| `McpRuntime` | builds and resets the server |
| `Guard` | `secret` or `oauth` |

## Security

- The endpoint is trusted-only. Without `endpoint_secret`, `client_secrets` or `oauth` every request is refused.
- Set `allowed_hosts` to the public hostname: it is the DNS-rebinding protection, only localhost is allowed without it.
- Several clients need `client_secrets`: a session belongs to the client that opened it.
- A failure inside a tool is logged; the client never gets its message.

## Examples

See [examples/](examples/): the script runs offline.

## Development

No PHP/Composer on the host, run in Docker via the `composer:2` image:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

Or with Make: `make build`, `make cs-fix`, `make psalm`, `make test`.

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
