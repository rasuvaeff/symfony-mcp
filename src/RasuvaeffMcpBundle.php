<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Wires `rasuvaeff/mcp` into Symfony from the `rasuvaeff_mcp` options.
 *
 * @api
 */
final class RasuvaeffMcpBundle extends Bundle
{
    #[\Override]
    public function getContainerExtension(): ExtensionInterface
    {
        return new RasuvaeffMcpExtension();
    }

    #[\Override]
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
