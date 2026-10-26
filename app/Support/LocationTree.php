<?php

namespace App\Support;

use App\Models\Location;
use Illuminate\Support\Collection;

/**
 * Lädt den Standortbaum einmal pro Request und beantwortet daraus Pfad- und
 * Unterbaum-Fragen, ohne für jeden Gegenstand eine rekursive Abfrage zu stellen.
 */
class LocationTree
{
    /** @var Collection<int, Location>|null */
    private ?Collection $locations = null;

    /** @var array<int, string> */
    private array $paths = [];

    /**
     * @return Collection<int, Location>
     */
    public function all(): Collection
    {
        return $this->locations ??= Location::query()->orderBy('name')->get()->keyBy('id');
    }

    public function path(?int $locationId, string $separator = ' › '): ?string
    {
        if ($locationId === null || ! $this->all()->has($locationId)) {
            return null;
        }

        return $this->paths[$locationId] ??= collect($this->ancestorsAndSelf($locationId))
            ->map(fn (Location $location) => $location->name)
            ->implode($separator);
    }

    /**
     * @return list<Location>
     */
    public function ancestorsAndSelf(int $locationId): array
    {
        $chain = [];
        $current = $this->all()->get($locationId);

        while ($current !== null) {
            array_unshift($chain, $current);
            $current = $current->parent_id === null ? null : $this->all()->get($current->parent_id);
        }

        return $chain;
    }

    /**
     * @return list<int>
     */
    public function descendantIdsAndSelf(int $locationId): array
    {
        $childrenByParent = $this->all()->groupBy('parent_id');
        $ids = [];
        $stack = [$locationId];

        while ($stack !== []) {
            $id = array_pop($stack);
            $ids[] = $id;

            foreach ($childrenByParent->get($id, collect()) as $child) {
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * Flache, nach Pfad sortierte Liste für Auswahlfelder: [id => "Gebäude › Raum › Regal"].
     *
     * @return array<int, string>
     */
    public function options(): array
    {
        return $this->all()
            ->mapWithKeys(fn (Location $location) => [$location->id => $this->path($location->id)])
            ->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))
            ->all();
    }

    /**
     * Baum als verschachtelte Liste für die Standortverwaltung.
     *
     * @return list<array{location: Location, depth: int}>
     */
    public function flattened(): array
    {
        $childrenByParent = $this->all()->groupBy(fn (Location $location) => $location->parent_id ?? 0);
        $rows = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$rows, $childrenByParent): void {
            foreach ($childrenByParent->get($parentId, collect())->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE) as $location) {
                $rows[] = ['location' => $location, 'depth' => $depth];
                $walk($location->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $rows;
    }
}
