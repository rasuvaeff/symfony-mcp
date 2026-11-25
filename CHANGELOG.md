# Changelog

## Unreleased

- Initial package: Symfony integration of `rasuvaeff/mcp` and `rasuvaeff/mcp-openapi`.
- Agent skill `rasuvaeff-symfony-mcp` in `resources/skills`.
- `ResourceUpdateNotifier` is a service.
- A `SessionStoreInterface` or `NotificationBusInterface` service of the application replaces the default.
- Requires `rasuvaeff/mcp` `^0.1.3`: while the backend of the cache pool is down, `set()` returns `false` and the server rejects the call instead of lifting the budget.
