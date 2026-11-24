<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp\Benchmarks;

use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\SymfonyMcp\Tests\Support\ClockTool;
use Rasuvaeff\SymfonyMcp\Tests\Support\SymfonyApplication;
use Testo\Bench;

final class McpControllerBench
{
    private static ?SymfonyApplication $application = null;

    public static function initialize(): int
    {
        return self::application()->psr(new ServerRequest('POST', 'http://localhost/mcp', [
            'X-Mcp-Secret' => 'secret',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
        ], '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"bench","version":"1"}}}'))->getStatusCode();
    }

    #[Bench(
        callables: [
            'initialize' => [self::class, 'initialize'],
        ],
        calls: 50,
        iterations: 5,
    )]
    public static function refusedWithoutASecret(): int
    {
        return self::application()->psr(new ServerRequest('POST', 'http://localhost/mcp'))->getStatusCode();
    }

    private static function application(): SymfonyApplication
    {
        return self::$application ??= SymfonyApplication::start(['tools' => [ClockTool::class], 'endpoint_secret' => 'secret']);
    }
}
