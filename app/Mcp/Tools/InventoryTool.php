<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

/**
 * Gemeinsame Basis: Kein Tool ist anonym nutzbar (R35). Ohne angemeldeten Nutzer
 * werden die Tools weder gelistet noch ausgeführt.
 */
abstract class InventoryTool extends Tool
{
    public function shouldRegister(Request $request): bool
    {
        return $request->user() instanceof User;
    }

    protected function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Für den MCP-Zugriff ist eine Anmeldung erforderlich.');
        }

        return $user;
    }
}
