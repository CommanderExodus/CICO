<?php

namespace Tests\Feature\Mcp;

use App\Enums\AuditChannel;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R33: Jeder MCP-Aufruf wird mit „via MCP“ und verwendetem Client protokolliert.
 */
class McpAuditTest extends TestCase
{
    use InteractsWithMcp;
    use RefreshDatabase;

    private function lastMcpEntry(): AuditLog
    {
        return AuditLog::query()->where('event', 'mcp.request')->latest('id')->firstOrFail();
    }

    public function test_tool_calls_are_logged_with_user_channel_and_client(): void
    {
        $user = User::factory()->create();

        $this->callTool($user, 'search-items', ['query' => 'beamer'], client: 'Claude Desktop')->assertOk();

        $entry = $this->lastMcpEntry();
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame(AuditChannel::Mcp, $entry->channel);
        $this->assertSame('Claude Desktop', $entry->client);
        $this->assertSame('tools/call', $entry->meta['method']);
        $this->assertSame('search-items', $entry->meta['tool']);
        $this->assertSame(['query' => 'beamer'], $entry->meta['arguments']);
        $this->assertSame('ok', $entry->meta['outcome']);
    }

    public function test_the_client_reported_during_initialize_is_recorded(): void
    {
        $this->mcp('initialize', [
            'protocolVersion' => '2025-11-25',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'claude-ai', 'version' => '0.1.0'],
        ], $this->tokenFor(User::factory()->create(), 'Mein Token'))->assertOk();

        $entry = $this->lastMcpEntry();
        $this->assertSame('claude-ai 0.1.0', $entry->client);
        $this->assertSame('Mein Token', $entry->meta['token']);
    }

    public function test_failed_calls_are_logged_as_errors(): void
    {
        $this->callTool(User::factory()->create(), 'check-availability', ['item_id' => 1, 'from' => 'gestern', 'to' => 'heute']);

        $this->assertSame('error', $this->lastMcpEntry()->meta['outcome']);
    }

    public function test_every_request_is_logged_not_only_tool_calls(): void
    {
        $token = $this->tokenFor(User::factory()->create());

        $this->mcp('tools/list', token: $token);
        $this->mcp('resources/list', token: $token);

        $this->assertSame(
            ['tools/list', 'resources/list'],
            AuditLog::query()->where('event', 'mcp.request')->orderBy('id')->get()->pluck('meta.method')->all(),
        );
    }
}
