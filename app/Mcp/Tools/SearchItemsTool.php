<?php

namespace App\Mcp\Tools;

use App\Enums\ItemAvailability;
use App\Enums\ItemKind;
use App\Models\Category;
use App\Models\Item;
use App\Queries\SearchItems;
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

#[Name('search-items')]
#[Title('Gegenstand suchen')]
#[Description('Durchsucht den Bestand nach Name, Seriennummer oder öffentlicher Kennung. Liefert je Gegenstand ID, Art, Kategorie, Standortpfad und aktuellen Zustand. Ein Standortfilter schließt Unterstandorte ein.')]
#[IsReadOnly]
#[IsIdempotent]
class SearchItemsTool extends InventoryTool
{
    public function handle(Request $request, SearchItems $search, LocationTree $locations): ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::enum(ItemKind::class)],
            'category' => ['nullable', 'string', 'max:80'],
            'location_id' => ['nullable', 'integer'],
            'availability' => ['nullable', Rule::enum(ItemAvailability::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $categoryId = null;

        if (filled($validated['category'] ?? null)) {
            $categoryId = Category::query()->where('name', 'ilike', $validated['category'])->value('id');

            if ($categoryId === null) {
                return Response::structured(['total' => 0, 'items' => [], 'hint' => 'Unbekannte Kategorie.']);
            }
        }

        $results = $search->handle($this->actor($request), [
            'search' => $validated['query'] ?? null,
            'kind' => $validated['kind'] ?? null,
            'category_id' => $categoryId,
            'location_id' => $validated['location_id'] ?? null,
            'availability' => $validated['availability'] ?? null,
        ], perPage: 20, page: (int) ($validated['page'] ?? 1));

        return Response::structured([
            'total' => $results->total(),
            'page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
            'items' => $results->getCollection()->map(fn (Item $item) => [
                'id' => $item->id,
                'public_id' => $item->public_id,
                'name' => $item->name,
                'kind' => $item->kind->value,
                'category' => $item->category?->name,
                'location' => $locations->path($item->location_id),
                'serial_number' => $item->serial_number,
                'quantity' => $item->quantity,
                'availability' => $item->availability()->value,
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Suchbegriff: Teil des Namens, Seriennummer oder öffentliche Kennung.'),
            'kind' => $schema->string()->enum(['unit', 'bulk'])->description('unit = Einzelgegenstand mit Seriennummer, bulk = Mengenware.'),
            'category' => $schema->string()->description('Name einer Kategorie, z. B. „Zelte“.'),
            'location_id' => $schema->integer()->description('ID eines Standorts; Unterstandorte werden eingeschlossen.'),
            'availability' => $schema->string()->enum(['available', 'on_loan', 'out_of_stock'])->description('Nur Gegenstände in diesem Zustand.'),
            'page' => $schema->integer()->min(1)->description('Ergebnisseite (20 Einträge pro Seite).'),
        ];
    }
}
