<?php

namespace Database\Seeders;

// database/seeders/SectionSeeder.php
use App\Models\Section;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    public function run()
    {
        $rows = [
            ['name' => 'BLOCK 1', 'code' => 'B1'],
            ['name' => 'BLOCK 2', 'code' => 'B2'],
            ['name' => 'BLOCK 3', 'code' => 'B3'],
            ['name' => 'BLOCK 4', 'code' => 'B4'],
        ];

        foreach ($rows as $row) {
            Section::updateOrCreate(['code' => $row['code']], $row);
        }
    }
}


