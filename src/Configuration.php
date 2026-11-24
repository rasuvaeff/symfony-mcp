<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The keys and their meaning belong to rasuvaeff/mcp and
 * rasuvaeff/mcp-openapi, which validate them when the server is built: a
 * second schema here would have to follow every one of them, and an `%env()%`
 * value has no type yet. Nested keys are kept as written — a tool named
 * "a-b" must not become "a_b". A later file replaces a top-level key as a whole.
 *
 * @internal
 */
final readonly class Configuration implements ConfigurationInterface
{
    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder(RasuvaeffMcpExtension::ALIAS);
        $root = $tree->getRootNode();
        $root->ignoreExtraKeys(remove: false);

        return $tree;
    }
}
