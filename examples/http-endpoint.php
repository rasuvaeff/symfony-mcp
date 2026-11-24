<?php

declare(strict_types=1);

use Mcp\Server\Capability\Attribute\McpTool;
use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\SymfonyMcp\Tests\Support\SymfonyApplication;

require dirname(__DIR__) . '/vendor/autoload.php';

final readonly class OrderTools
{
    #[McpTool(name: 'order.status')]
    public function status(string $orderId): string
    {
        return $orderId === '42' ? 'shipped' : 'unknown';
    }
}

// a real Symfony application on a throwaway directory
$application = SymfonyApplication::start([
    'tools' => [OrderTools::class],
    'endpoint_secret' => 'example-secret',
]);

$call = static fn(array $headers): string => (string) $application->psr(new ServerRequest('POST', 'http://localhost/mcp', $headers + [
    'Content-Type' => 'application/json',
    'Accept' => 'application/json, text/event-stream',
    'MCP-Protocol-Version' => '2026-07-28',
    'Mcp-Method' => 'tools/call',
    'Mcp-Name' => 'order.status',
], json_encode([
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'tools/call',
    'params' => [
        'name' => 'order.status',
        'arguments' => ['orderId' => '42'],
        '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => new stdClass()],
    ],
], JSON_THROW_ON_ERROR)))->getBody();

echo 'without the secret -> ', $call([]), "\n";
echo 'with the secret    -> ', $call(['X-Mcp-Secret' => 'example-secret']), "\n";
