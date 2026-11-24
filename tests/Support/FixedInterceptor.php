<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp\Tests\Support;

use Rasuvaeff\Mcp\Interceptor\ToolCallContext;
use Rasuvaeff\Mcp\Interceptor\ToolCallInterceptorInterface;

final readonly class FixedInterceptor implements ToolCallInterceptorInterface
{
    #[\Override]
    public function intercept(ToolCallContext $context, callable $next): mixed
    {
        return 'intercepted';
    }
}
