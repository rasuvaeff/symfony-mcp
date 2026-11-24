<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Mcp\Testing\AdapterContract;
use Rasuvaeff\Mcp\Testing\AdapterContractDependency;
use Rasuvaeff\Mcp\Testing\AdapterContractTool;
use Rasuvaeff\SymfonyMcp\McpController;
use Rasuvaeff\SymfonyMcp\RasuvaeffMcpExtension;
use Rasuvaeff\SymfonyMcp\Tests\Support\SymfonyApplication;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * The shared adapter contract through Symfony's own HTTP kernel.
 */
#[Test]
#[Covers(RasuvaeffMcpExtension::class)]
#[Covers(McpController::class)]
final class AdapterContractTest
{
    private const array SECRETS = ['alpha' => 'secret-a', 'beta' => 'secret-b'];

    private static ?AdapterContract $contract = null;

    #[DataProvider('checkProvider')]
    public function keepsTheAdapterContract(string $check): void
    {
        self::contract()->checks()[$check]();
        Assert::true(actual: true);
    }

    public static function checkProvider(): iterable
    {
        foreach (array_keys(self::contract()->checks()) as $check) {
            yield $check => [$check];
        }
    }

    private static function contract(): AdapterContract
    {
        $factory = new Psr17Factory();

        return self::$contract ??= new AdapterContract(
            handle: SymfonyApplication::start([
                'tools' => [AdapterContractTool::class],
                'client_secrets' => self::SECRETS,
            ], [AdapterContractDependency::class])->psr(...),
            requestFactory: $factory,
            streamFactory: $factory,
            uri: 'http://localhost/mcp',
            secrets: self::SECRETS,
        );
    }
}
