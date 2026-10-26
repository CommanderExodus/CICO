<?php

use App\Mcp\Servers\InventoryServer;
use Laravel\Mcp\Facades\Mcp;

// R25/R26: per HTTP erreichbar, aber nur mit persönlichem Sanctum-Token.
Mcp::web('/mcp', InventoryServer::class)->middleware('auth:sanctum');
