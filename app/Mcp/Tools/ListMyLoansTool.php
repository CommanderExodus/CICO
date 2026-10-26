<?php

namespace App\Mcp\Tools;

use App\Models\Loan;
use App\Queries\ListLoans;
use App\Support\LocationTree;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-my-loans')]
#[Title('Eigene Ausleihen anzeigen')]
#[Description('Listet die Ausleihen und Reservierungen des angemeldeten Nutzers. „overdue“ ist abgeleitet: ausgegeben und Rückgabedatum überschritten.')]
#[IsReadOnly]
#[IsIdempotent]
class ListMyLoansTool extends InventoryTool
{
    public function handle(Request $request, ListLoans $loans, LocationTree $locations): ResponseFactory
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(ListLoans::FILTERS)],
        ]);

        $actor = $this->actor($request);

        $results = $loans->query($actor, [
            'mine' => true,
            'status' => $validated['status'] ?? 'active',
        ])->limit(100)->get();

        return Response::structured([
            'count' => $results->count(),
            'loans' => $results->map(fn (Loan $loan) => [
                'id' => $loan->id,
                'item' => ['id' => $loan->item_id, 'name' => $loan->item->name, 'location' => $locations->path($loan->item->location_id)],
                'starts_on' => $loan->starts_on->toDateString(),
                'ends_on' => $loan->ends_on->toDateString(),
                'status' => $loan->status->value,
                'overdue' => $loan->isOverdue(),
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(ListLoans::FILTERS)
                ->description('Filter: active (Standard: reserviert oder ausgegeben), reserved, issued, overdue, returned, cancelled oder all.'),
        ];
    }
}
