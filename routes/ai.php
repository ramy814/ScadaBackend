<?php

if (class_exists(\Laravel\Mcp\Facades\Mcp::class)) {
    \Laravel\Mcp\Facades\Mcp::oauthRoutes();

    \Laravel\Mcp\Facades\Mcp::local('fleet', \App\Mcp\Servers\FleetServer::class);

    \Laravel\Mcp\Facades\Mcp::web('/mcp/fleet', \App\Mcp\Servers\FleetServer::class);
}

