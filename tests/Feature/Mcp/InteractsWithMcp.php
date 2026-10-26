<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Spricht den MCP-Server über den echten HTTP-Endpunkt an – mit persönlichem
 * Sanctum-Token, genau wie ein entfernter Client (R25, R26).
 */
trait InteractsWithMcp
{
    /**
     * @param  array<string, mixed>  $params
     */
    protected function mcp(string $method, array $params = [], ?string $token = null): TestResponse
    {
        $headers = $token === null ? [] : ['Authorization' => "Bearer {$token}"];

        return $this->withHeaders($headers + ['Accept' => 'application/json, text/event-stream'])
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => $method,
                'params' => $params === [] ? (object) [] : $params,
            ]);
    }

    protected function tokenFor(User $user, string $name = 'Test-Client'): string
    {
        return $user->createToken($name, ['mcp'])->plainTextToken;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function callTool(User $user, string $tool, array $arguments = [], string $client = 'Test-Client'): TestResponse
    {
        return $this->mcp('tools/call', ['name' => $tool, 'arguments' => (object) $arguments], $this->tokenFor($user, $client));
    }
}
