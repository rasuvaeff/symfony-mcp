<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp;

use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The route action: the guard, then the MCP endpoint, over PSR-7.
 *
 * @api
 */
final readonly class McpController
{
    public function __construct(
        private McpRuntime $runtime,
    ) {}

    public function __invoke(Request $request): Response
    {
        $factory = new Psr17Factory();
        $response = $this->runtime->guard()->process(
            (new PsrHttpFactory($factory, $factory, $factory, $factory))->createRequest($request),
            $this->runtime->action(),
        );

        // a listen stream must leave frame by frame, not as one buffered body
        return (new HttpFoundationFactory())->createResponse(
            $response,
            streamed: str_starts_with($response->getHeaderLine('Content-Type'), 'text/event-stream'),
        );
    }
}
