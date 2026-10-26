<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Konten, die auf Freischaltung warten, landen auf einer Hinweisseite statt im Inventar.
 */
class EnsureUserIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isPending()) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 403, 'Dein Konto wartet auf Freischaltung.');

        return redirect()->route('pending');
    }
}
