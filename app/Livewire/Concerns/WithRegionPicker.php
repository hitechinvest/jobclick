<?php

namespace App\Livewire\Concerns;

use App\Models\Region;
use Livewire\Attributes\Renderless;

/** Выбор городов/регионов из справочника Вордстата. */
trait WithRegionPicker
{
    /** @var array<int, int> */
    public array $regionIds = [];

    #[Renderless]
    public function searchRegions(string $query): array
    {
        return Region::search($query, 10)->map(fn (Region $r) => ['id' => $r->id, 'label' => $r->label()])->all();
    }

    public function addRegion(int $id): void
    {
        if (! Region::whereKey($id)->exists() || in_array($id, $this->regionIds, true) || count($this->regionIds) >= 50) {
            return;
        }
        // «Вся Россия» исключает остальные, и наоборот
        $this->regionIds = $id === Region::RUSSIA ? [Region::RUSSIA] : array_values(array_diff($this->regionIds, [Region::RUSSIA]));
        if ($id !== Region::RUSSIA) {
            $this->regionIds[] = $id;
        }
        $this->regionsChanged();
    }

    public function removeRegion(int $id): void
    {
        $this->regionIds = array_values(array_diff($this->regionIds, [$id]));
        $this->regionsChanged();
    }

    protected function regionsChanged(): void {}

    public function selectedRegions(): array
    {
        if (! $this->regionIds) {
            return [];
        }
        $regions = Region::whereIn('id', $this->regionIds)->get()->keyBy('id');

        return collect($this->regionIds)->map(fn ($id) => $regions->get($id))->filter()
            ->map(fn (Region $r) => ['id' => $r->id, 'label' => $r->name])->values()->all();
    }
}
