<?php

namespace Database\Seeders;

use App\Models\Plot;
use App\Models\Section;
use Illuminate\Database\Seeder;

class PlotSeeder extends Seeder
{
    public function run(): void
    {
        // Find sections by code (we created them above)
        $sectionA = Section::where('code', 'A')->first();
        $sectionB = Section::where('code', 'B')->first();

        if (!$sectionA || !$sectionB) return;

        // Helper: simple square around a center (Leaflet wants lon/lat order)
        $poly = function ($lon, $lat, $size = 0.00002) {
            return [
                'type' => 'Polygon',
                'coordinates' => [[
                    [$lon, $lat],
                    [$lon + $size, $lat],
                    [$lon + $size, $lat + $size],
                    [$lon, $lat + $size],
                    [$lon, $lat],
                ]],
            ];
        };

        // Sample coordinates near Bethany Tubigon; adjust as needed
        $baseLon = 124.0600; $baseLat = 9.7440;

        // Create 10 demo plots in Section A
        for ($i = 1; $i <= 10; $i++) {
            $lon = $baseLon + ($i * 0.00003);
            $lat = $baseLat + ($i * 0.00002);

            Plot::firstOrCreate(
                ['section_id' => $sectionA->id, 'lot_number' => "A-{$i}"],
                [
                    'status' => 'vacant',
                    'geojson' => json_encode($poly($lon, $lat)),
                ]
            );
        }

        // A few occupied/reserved samples in Section B
        foreach ([
            ['num' => 1, 'status' => 'occupied', 'name' => 'Juan dela Cruz'],
            ['num' => 2, 'status' => 'reserved', 'name' => null],
        ] as $p) {
            $lon = $baseLon + ($p['num'] * 0.000025);
            $lat = $baseLat - ($p['num'] * 0.00002);

            Plot::firstOrCreate(
                ['section_id' => $sectionB->id, 'lot_number' => "B-{$p['num']}"],
                [
                    'status' => $p['status'],
                    'occupant_name' => $p['name'],
                    'geojson' => json_encode($poly($lon, $lat, 0.000018)),
                ]
            );
        }
    }
}
