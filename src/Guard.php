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
}
