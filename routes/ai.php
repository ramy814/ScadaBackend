<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::local('fleet', \App\Mcp\Servers\FleetServer::class);

Mcp::web('/mcp/fleet', \App\Mcp\Servers\FleetServer::class);

