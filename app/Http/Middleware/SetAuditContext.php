<?php

namespace App\Http\Middleware;

use App\Enums\AuditChannel;
use App\Support\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ordnet Änderungen aus Web-Requests dem angemeldeten Nutzer zu. Requests auf
 * die öffentliche Statusseite laufen als Kanal „public“ ohne Nutzer.
 */
class SetAuditContext
{
    public function __construct(private AuditContext $context)
    {
        //
    }

    public function handle(Request $request, Closure $next): Response
    {
        $public = $request->routeIs('public.*');

        $this->context->set(
            $public ? null : $request->user(),
            $public ? AuditChannel::Public : AuditChannel::Web,
        );

        return $next($request);
    }
}
