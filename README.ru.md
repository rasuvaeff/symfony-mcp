# rasuvaeff/symfony-mcp

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/symfony-mcp?label=stable&sort_semver=1)](https://packagist.org/packages/rasuvaeff/symfony-mcp)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/symfony-mcp)](https://packagist.org/packages/rasuvaeff/symfony-mcp)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/symfony-mcp/build.yml?branch=master)](https://github.com/rasuvaeff/symfony-mcp/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/symfony-mcp/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/symfony-mcp/actions)
[![Psalm level](https://img.shields.io/badge/psalm-level%201-141F48?logo=psalm&logoColor=white)](https://github.com/rasuvaeff/symfony-mcp/blob/master/psalm.xml)
[![License](https://img.shields.io/packagist/l/rasuvaeff/symfony-mcp)](LICENSE.md)

[English version](README.md)

Интеграция [`rasuvaeff/mcp`](https://github.com/rasuvaeff/mcp) с Symfony: сервер Model Context
Protocol поверх официального [`mcp/sdk`](https://packagist.org/packages/mcp/sdk). Инструменты из
контейнера, HTTP-эндпоинт, консольные команды и
[мост OpenAPI](https://github.com/rasuvaeff/mcp-openapi).

[`symfony/mcp-bundle`](https://github.com/symfony/mcp-bundle) — официальный бандл на том же `mcp/sdk`. Берите его, если не нужно то, ради чего сделан этот: один и тот же код сервера в нескольких фреймворках, мост OpenAPI, интерсепторы и видимость из `rasuvaeff/mcp`.

> **Пользуетесь ИИ-ассистентом?** [llms.txt](llms.txt) — компактный справочник по API
> для модели. Участникам: см. [AGENTS.md](AGENTS.md). В проектах с Composer-плагином
> [llm/skills](https://github.com/roxblnfk/skills) скилл пакета сам появляется в
> `.agents/skills/` при установке.

## Требования

| Требование | Версия |
|---|---|
| PHP | 8.3 – 8.6 |
| Symfony | `^7.4 \|\| ^8.0`; части компонентов нужен более поздний патч-релиз без security advisories, см. `composer.json` |
| `rasuvaeff/mcp` | `^0.1.4` |
| `rasuvaeff/mcp-openapi` | `^0.1.3` |
| `firebase/php-jwt` | необязательно, `^7.0`: проверка JWT для OAuth |

## Установка

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

## Использование

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
parameters:
    # without a default an unset variable fails the request instead of the 503 below
    env(MCP_SECRET): ''
rasuvaeff_mcp:
    server_name: my-app
    tools: ['App\Mcp\OrderTools']
    endpoint_secret: '%env(MCP_SECRET)%'
    allowed_hosts: ['mcp.example.com']
```

Эндпоинт — `/mcp`. Без секрета он на всё отвечает 503.

```bash
bin/console mcp:list    # what is served
bin/console mcp:serve   # stdio, for a local agent
```

### Конфигурация

`guard` — `secret` или `oauth`. Остальные ключи те же, что в `rasuvaeff/yii3-mcp`; см.
[документацию](https://rasuvaeff.github.io/mcp/). Значения проверяются при первой сборке
сервера, а не при компиляции контейнера, поэтому `%env()%` работает везде, кроме имён классов
и `notifications.bus`. Следующий файл конфигурации заменяет ключ верхнего уровня целиком.

`ProtectedResourceMetadataHandler` (сервис) отдайте своим маршрутом по адресу
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

Код вне MCP объявляет об изменении ресурса через сервис `ResourceUpdateNotifier`
(нужна `notifications.bus`).

### Несколько узлов

Вызовам без сессии ничего не нужно. Для сессий объявите общее хранилище; уведомления и лимит
вызовов работают через кеш приложения, направьте его в Redis:

```yaml
# config/services.yaml
services:
    Mcp\Server\Session\SessionStoreInterface:
        class: Mcp\Server\Session\Psr6SessionStore
        arguments: ['@cache.app']
```

Лимит вызовов считается через PSR-16, где нет атомарного инкремента: одновременные вызовы могут
пройти все. Он останавливает цикл, но это не квота.

Пул кеша Symfony скрывает отказ хранилища: пока Redis недоступен, `set()` возвращает `false` вместо исключения. Сервер считает это неудачной записью и отклоняет вызов: недоступное хранилище закрывает доступ, а не снимает лимит.

Keycloak добавляет ваш эндпоинт в `aud` только через audience mapper клиента; без него любой
токен получит 401.

Пока хранилище сессий недоступно, `initialize` всё равно отвечает с id сессии: SDK пишет неудачное сохранение в лог, следующий запрос с этим id получает 404, после чего клиент открывает новую сессию.

### Долгоживущие воркеры

- Проверено на FrankenPHP (обычный режим и worker), Swoole и RoadRunner. Под RoadRunner поток `subscriptions/listen` приходит целиком, когда закончится: мост PSR-7 собирает всё тело. Держите там `notifications.subscription_lifetime` коротким или отдавайте потоки другим сервером.
- `guard` кроме `secret` и `oauth`, в том числе пустой, даёт ошибку `Unknown MCP guard`.
- Классы из настроек регистрируются приватными с автосвязыванием; ваш сервис с тем же id важнее.
- Сервер сбрасывается через `kernel.reset` между запросами воркера. Сессии на диске и шина уведомлений остаются.
- Объект инструмента — обычный сервис и живёт между запросами воркера: его поля увидит следующий клиент. Не храните в них состояние запроса или реализуйте `ResetInterface`.
- Не исключайте класс инструмента через `exclude:` в `services.yaml`: контейнер не соберётся («reference to an abstract definition»).
- Алиас настроек — `rasuvaeff_mcp`: `mcp` занят `symfony/mcp-bundle`.

### Компоненты

| Класс | Роль |
|---|---|
| `RasuvaeffMcpBundle` | бандл |
| `McpController` | действие маршрута: проверка доступа, затем эндпоинт MCP |
| `McpRuntime` | собирает и сбрасывает сервер |
| `Guard` | `secret` или `oauth` |

## Безопасность

- Эндпоинт только для доверенных клиентов. Без `endpoint_secret`, `client_secrets` или `oauth` каждый запрос отклоняется.
- Укажите в `allowed_hosts` публичное имя: это защита от DNS rebinding, без неё разрешён только localhost.
- Нескольким клиентам нужны `client_secrets`: сессия принадлежит клиенту, который её открыл.
- Сбой внутри инструмента пишется в лог, его текст клиент не получает.

## Примеры

См. [examples/](examples/): скрипт работает без сети.

## Разработка

PHP и Composer на хосте не нужны, всё в Docker через образ `composer:2`:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

Или через Make: `make build`, `make cs-fix`, `make psalm`, `make test`.

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
