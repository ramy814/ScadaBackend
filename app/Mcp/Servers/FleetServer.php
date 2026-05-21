<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Fleet Manager')]
#[Version('1.0.0')]
#[Instructions('Tools for managing fleet vehicles, drivers, and trips.')]
class FleetServer extends Server
{
    protected array $tools = [
        \App\Mcp\Tools\SearchVehiclesTool::class,
        \App\Mcp\Tools\SearchDriversTool::class,
        \App\Mcp\Tools\ListRecentTripsTool::class,
        \App\Mcp\Tools\GetTripDetailsTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
