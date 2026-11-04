<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

/**
 * Регионы России из дерева Яндекс Вордстата (getRegionsTree). Коды совпадают с гео Яндекс Директа.
 */
class RegionSeeder extends Seeder
{
    /** Крупные города показываем первыми в подсказках. */
    protected const TOP = [225 => 0, 213 => 1, 2 => 2, 65 => 10, 54 => 10, 43 => 10, 47 => 10, 56 => 10, 62 => 10,
        51 => 10, 172 => 10, 39 => 10, 66 => 10, 35 => 10, 193 => 10, 50 => 10, 38 => 10];

    public function run(): void
    {
        $rows = json_decode(file_get_contents(database_path('data/regions.json')), true);

        Region::query()->delete();
        foreach (array_chunk($rows, 300) as $chunk) {
            Region::insert(array_map(fn ($r) => [
                'id' => $r[0],
                'parent_id' => $r[1],
                'name' => $r[2],
                'parent_name' => $r[3],
                'depth' => $r[4],
                'sort' => self::TOP[$r[0]] ?? match (true) {
                    $r[4] >= 3 => 100,
                    $r[4] === 2 => 200,
                    default => 300,
                },
            ], $chunk));
        }
    }
}
