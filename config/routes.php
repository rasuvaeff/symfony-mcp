<?php

declare(strict_types=1);

use Rasuvaeff\SymfonyMcp\McpController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// the endpoint at "/mcp"; import it under a prefix or declare your own route to move it
return static function (RoutingConfigurator $routes): void {
    $routes->add('rasuvaeff_mcp', '/mcp')
        ->controller(McpController::class)
        ->methods(['GET', 'POST', 'DELETE', 'OPTIONS']);
};
