<?php

namespace App\Mcp\Tools;

use App\Models\Item;
use App\Queries\CheckAvailability;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('check-availability')]
#[Title('Verfügbarkeit prüfen')]
#[Description('Prüft, ob ein Einzelgegenstand in einem Zeitraum (inklusive Start- und Endtag) frei ist, und nennt belegte Zeiträume. Namen anderer Ausleiher sind nur für Verwalter sichtbar.')]
#[IsReadOnly]
#[IsIdempotent]
class CheckAvailabilityTool extends InventoryTool
{
    public function handle(Request $request, CheckAvailability $availability): ResponseFactory
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $item = Item::query()->findOrFail($validated['item_id']);

        $result = $availability->handle(
            $this->actor($request),
            $item,
            Carbon::createFromFormat('Y-m-d', $validated['from'])->startOfDay(),
            Carbon::createFromFormat('Y-m-d', $validated['to'])->startOfDay(),
        );

        return Response::structured([
            'item' => ['id' => $item->id, 'name' => $item->name],
            'from' => $validated['from'],
            'to' => $validated['to'],
            ...$result,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'item_id' => $schema->integer()->description('ID des Gegenstands (aus search-items).')->required(),
            'from' => $schema->string()->format('date')->description('Erster Tag, JJJJ-MM-TT.')->required(),
            'to' => $schema->string()->format('date')->description('Letzter Tag, JJJJ-MM-TT.')->required(),
        ];
    }
}
