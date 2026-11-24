<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp\Tests\Support;

use Mcp\Server\Capability\Attribute\McpTool;

final readonly class ClockTool
{
    #[McpTool(name: 'clock')]
    public function now(): string
    {
        return 'noon';
    }
}
