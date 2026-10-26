<?php

use App\Http\Middleware\EnsureUserIsApproved;
use App\Http\Middleware\SetAuditContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetAuditContext::class,
        ]);

        $middleware->alias([
            'approved' => EnsureUserIsApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Der MCP-Endpunkt antwortet immer maschinenlesbar – ohne Token mit 401 statt einer
        // Weiterleitung auf die Login-Seite, auch wenn der Client kein JSON-Accept sendet.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('mcp') || $request->expectsJson());
    })->create();
