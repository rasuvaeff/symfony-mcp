# AGENTS.md — symfony-mcp

Guidance for AI agents working on this package. Read before changing code.

## What this is

Symfony integration of `rasuvaeff/mcp` (namespace `Rasuvaeff\SymfonyMcp`): `RasuvaeffMcpBundle` — the bundle; `McpController` — route action: guard, then the MCP endpoint; `McpRuntime` — builds and resets the server; `Guard` — `secret` or `oauth`.
The meaning of every option belongs to `rasuvaeff/mcp` and `rasuvaeff/mcp-openapi`
(`Configuration\ServerOptions`, `Configuration\ServerComponentResolver`,
`OpenApiConfiguratorResolver`); this package only wires them.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Read option values at run time.** The extension may look at class names and `notifications.bus` only: anything else may be an unresolved `%env()%`. A class resolved by `rasuvaeff/mcp` or `rasuvaeff/mcp-openapi` must be listed in `ServiceClasses`, or it is not in the locator.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
make test-coverage
make mutation
make release-check
```

`composer.lock` is gitignored (library).
`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## Invariants & gotchas

- `tests/AdapterContractTest.php` runs `Rasuvaeff\Mcp\Testing\AdapterContract` through the real kernel: it must stay green.
- `McpRuntime::reset()` drops the server only; sessions and the bus outlive a request.
- Nested option keys are never normalized: a tool named `a-b` stays `a-b`.
- SSE answers stay streamed.
- The agent skill in `resources/skills` and `llms.txt` change together with the API.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.
- **Validation patterns anchor with `\z`, never `$`.** In PCRE `$` also matches
  immediately before a trailing newline, so `/^…$/` silently accepts one `\n` —
  the value the whitelist was supposed to reject then travels on as if it were
  clean. `/D` does the same job. Cover it with a data-provider case carrying
  `"value\n"`, or the regression comes back unnoticed.
- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes.
- **CI workflows are SHA-pinned.** Every `uses:` in `.github/workflows/*.yml`
  references a 40-char commit SHA with a `# vN` trailing comment
  (e.g. `actions/checkout@<sha> # v4`). Never revert to floating `@vN` tags.
  Updates go through Dependabot, which bumps the SHA and preserves the comment.
  Workflows also carry `permissions: { contents: read }` at workflow level and
  `persist-credentials: false` on every `actions/checkout` step. Verify with
  `zizmor --persona=auditor .github/` — must report no `unpinned-uses`,
  `excessive-permissions`, or `artipacked` findings.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build`; if the change affects the public API or release
  process, also run `make release-check`. Paste the output.
