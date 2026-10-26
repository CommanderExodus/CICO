<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\InventoryServer;
use App\Mcp\Tools\CheckAvailabilityTool;
use App\Mcp\Tools\ListMyLoansTool;
use App\Mcp\Tools\SearchItemsTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R25, R26, R35: MCP per HTTP, nur mit Login, ohne anonyme Tools.
 */
class McpAccessTest extends TestCase
{
    use InteractsWithMcp;
    use RefreshDatabase;

    public function test_requests_without_a_token_are_rejected(): void
    {
        $this->mcp('tools/list')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');
    }

    public function test_unauthenticated_requests_never_redirect_to_the_login_page(): void
    {
        $this->call('POST', '/mcp', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'text/event-stream'], content: '{"jsonrpc":"2.0","id":1,"method":"tools/list"}')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');
    }

    public function test_an_invalid_or_revoked_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->mcp('tools/list', token: 'falsch|token')->assertUnauthorized();

        $user->tokens()->delete();
        $this->mcp('tools/list', token: $token)->assertUnauthorized();
    }

    public function test_a_personal_token_grants_access_over_http(): void
    {
        $token = $this->tokenFor(User::factory()->create());

        $this->mcp('initialize', [
            'protocolVersion' => '2025-11-25',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'claude-ai', 'version' => '1.0'],
        ], $token)
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'CICO Inventar');

        $tools = $this->mcp('tools/list', token: $token)->assertOk()->json('result.tools');

        $this->assertEqualsCanonicalizing(
            ['search-items', 'check-availability', 'list-my-loans'],
            array_column($tools, 'name'),
        );
    }

    public function test_all_tools_in_the_mvp_are_annotated_as_read_only(): void
    {
        $tools = $this->mcp('tools/list', token: $this->tokenFor(User::factory()->create()))->json('result.tools');

        foreach ($tools as $tool) {
            $this->assertTrue($tool['annotations']['readOnlyHint'] ?? false, "{$tool['name']} ist nicht als read-only annotiert.");
            $this->assertTrue($tool['annotations']['idempotentHint'] ?? false, "{$tool['name']} ist nicht als idempotent annotiert.");
        }
    }

    public function test_there_are_no_anonymous_tools(): void
    {
        InventoryServer::tools()->assertNotRegistered([SearchItemsTool::class, CheckAvailabilityTool::class, ListMyLoansTool::class]);

        InventoryServer::actingAs(User::factory()->create())
            ->tools()
            ->assertRegistered([SearchItemsTool::class, CheckAvailabilityTool::class, ListMyLoansTool::class]);
    }

    public function test_the_public_status_page_is_not_exposed_via_mcp(): void
    {
        $token = $this->tokenFor(User::factory()->create());

        $this->assertSame([], $this->mcp('resources/list', token: $token)->json('result.resources'));
        $this->assertSame([], $this->mcp('prompts/list', token: $token)->json('result.prompts'));

        $this->mcp('tools/call', ['name' => 'report-found-item', 'arguments' => (object) []], $token)
            ->assertJsonPath('error.code', -32602);
    }
}
