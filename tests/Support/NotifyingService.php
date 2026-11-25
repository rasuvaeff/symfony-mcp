<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp\Tests\Support;

use Rasuvaeff\Mcp\Resource\ResourceUpdateNotifier;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final readonly class NotifyingService
{
    public function __construct(public ResourceUpdateNotifier $notifier) {}
}
