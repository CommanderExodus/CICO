<?php

namespace App\Mcp\Servers;

use App\Enums\AuditChannel;
use App\Mcp\Tools\CheckAvailabilityTool;
use App\Mcp\Tools\ListMyLoansTool;
use App\Mcp\Tools\SearchItemsTool;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditContext;
use Illuminate\Support\Arr;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * MCP-Server für KI-Assistenten (R25). Er ist nur mit Login erreichbar (R26),
 * alle Tools laufen unter dem Konto des Nutzers und rufen dieselben Queries und
 * Actions wie die Weboberfläche auf (R27). Die öffentliche Statusseite ist hier
 * bewusst nicht abgebildet (R35).
 */
#[Name('CICO Inventar')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Zugriff auf das Inventar- und Ausleihsystem eines Vereins. Alle Aufrufe laufen
    unter dem Konto des angemeldeten Nutzers und haben genau dessen Rechte.
    Mit search-items Gegenstände finden (liefert die IDs), mit check-availability
    prüfen, ob ein Einzelgegenstand in einem Zeitraum frei ist, und mit
    list-my-loans die eigenen Ausleihen abrufen. Datumsangaben im Format JJJJ-MM-TT.
    MARKDOWN)]
class InventoryServer extends Server
{
    protected array $tools = [
        SearchItemsTool::class,
        CheckAvailabilityTool::class,
        ListMyLoansTool::class,
    ];

    /**
     * Jeder MCP-Aufruf läuft im Audit-Kontext „via MCP“ und wird protokolliert (R33).
     */
    protected function runMethodHandle(JsonRpcRequest $request, ServerContext $context): iterable|JsonRpcResponse
    {
        $user = auth()->user();
        $client = $this->describeClient($request, $user instanceof User ? $user : null);

        app(AuditContext::class)->set($user, AuditChannel::Mcp, $client['label']);

        $response = null;

        try {
            return $response = parent::runMethodHandle($request, $context);
        } catch (Throwable $e) {
            $response = $e;

            throw $e;
        } finally {
            if ($request->method !== 'ping') {
                $this->audit($request, $user instanceof User ? $user : null, $client, $response);
            }
        }
    }

    /**
     * @param  array{label: string, client_info: ?string, token: ?string, user_agent: ?string}  $client
     */
    protected function audit(JsonRpcRequest $request, ?User $user, array $client, mixed $response): void
    {
        $failed = $response instanceof Throwable
            || ($response instanceof JsonRpcResponse && (
                isset($response->content['error']) || ($response->content['result']['isError'] ?? false) === true
            ));

        AuditLog::query()->create([
            'event' => 'mcp.request',
            'user_id' => $user?->id,
            'channel' => AuditChannel::Mcp,
            'client' => $client['label'],
            'meta' => array_filter([
                'method' => $request->method,
                'tool' => $request->method === 'tools/call' ? $request->get('name') : null,
                'arguments' => $request->method === 'tools/call' ? $request->get('arguments', []) : null,
                'outcome' => $failed ? 'error' : 'ok',
                'client_info' => $client['client_info'],
                'token' => $client['token'],
                'user_agent' => $client['user_agent'],
            ], fn ($value) => $value !== null),
        ]);
    }

    /**
     * Der „verwendete Client“ (R33): bevorzugt die clientInfo, die der Client selbst meldet,
     * sonst der Name des Tokens, mit dem er sich angemeldet hat, sonst der User-Agent.
     *
     * @return array{label: string, client_info: ?string, token: ?string, user_agent: ?string}
     */
    protected function describeClient(JsonRpcRequest $request, ?User $user): array
    {
        $info = Arr::get($request->meta() ?? [], MetaKey::CLIENT_INFO->value)
            ?? ($request->method === 'initialize' ? $request->get('clientInfo') : null);

        $clientInfo = is_array($info) && filled($info['name'] ?? null)
            ? trim($info['name'].' '.($info['version'] ?? ''))
            : null;

        $token = $user?->currentAccessToken();
        $tokenName = $token instanceof PersonalAccessToken ? $token->name : null;
        $userAgent = request()->userAgent();

        return [
            'label' => $clientInfo ?? $tokenName ?? $userAgent ?? 'unbekannt',
            'client_info' => $clientInfo,
            'token' => $tokenName,
            'user_agent' => $userAgent,
        ];
    }
}
