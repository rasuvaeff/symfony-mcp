# Changelog

## 0.1.1 — 2026-11-27

- Requires `symfony/cache` `^7.4.12 || ^8.0.12`, `symfony/config` and `symfony/http-foundation` `^7.4.23 || ^8.1.11`, `symfony/http-kernel` `^7.4.24 || ^8.1.12`: versions with security advisories are no longer allowed.
- Docs: the configuration example gives `MCP_SECRET` a default, so an unset variable gets the documented 503, not a 500 from `EnvNotFoundException`.

## 0.1.0 — 2026-11-26

- Initial package: Symfony integration of `rasuvaeff/mcp` and `rasuvaeff/mcp-openapi`.
- Agent skill `rasuvaeff-symfony-mcp` in `resources/skills`.
- `ResourceUpdateNotifier` is a service.
- A `SessionStoreInterface` or `NotificationBusInterface` service of the application replaces the default.
- Requires `rasuvaeff/mcp` `^0.1.3`: while the backend of the cache pool is down, `set()` returns `false` and the server rejects the call instead of lifting the budget.
- An unknown or empty `guard` fails with `Unknown MCP guard` instead of a `ValueError`.
- Documented: Swoole and RoadRunner checked; under RoadRunner a listen stream arrives when it ends.
- Requires `rasuvaeff/mcp` `^0.1.4`: a listen stream no longer ends the output buffer of Swoole or of the PSR-7 capture under RoadRunner.
