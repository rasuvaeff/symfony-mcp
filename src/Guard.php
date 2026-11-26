<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp;

/**
 * What protects the endpoint, the `guard` option.
 *
 * @api
 */
enum Guard: string
{
    /** `endpoint_secret` or `client_secrets`; refuses everything while neither is set */
    case Secret = 'secret';

    /** OAuth 2.1 resource server, the `oauth` block */
    case OAuth = 'oauth';

    /**
     * The `guard` value: an empty one (an unset environment variable) is not taken for the default.
     *
     * @throws \InvalidArgumentException on any other value
     */
    public static function fromConfig(mixed $value): self
    {
        return self::tryFrom(\is_string($value) ? $value : '')
            ?? throw new \InvalidArgumentException(\sprintf('Unknown MCP guard %s: use "secret" or "oauth"', \is_string($value) ? '"' . $value . '"' : get_debug_type($value)));
    }
}
