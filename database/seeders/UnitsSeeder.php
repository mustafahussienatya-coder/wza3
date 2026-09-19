<?php

namespace Database\Seeders;

use App\Modules\Units\Models\Unit;
use Illuminate\Database\Seeder;

class UnitsSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'جرام', 'symbol' => 'جم', 'decimal_places' => 0, 'is_weight' => true],
            ['name' => 'كيلوجرام', 'symbol' => 'كجم', 'decimal_places' => 3, 'is_weight' => true],
            ['name' => 'طن', 'symbol' => 'طن', 'decimal_places' => 3, 'is_weight' => true],
            ['name' => 'قطعة', 'symbol' => 'قطعة', 'decimal_places' => 0, 'is_weight' => false],
            ['name' => 'عبوة', 'symbol' => 'عبوة', 'decimal_places' => 0, 'is_weight' => false],
            ['name' => 'كيس', 'symbol' => 'كيس', 'decimal_places' => 0, 'is_weight' => false],
            ['name' => 'علبة', 'symbol' => 'علبة', 'decimal_places' => 0, 'is_weight' => false],
            ['name' => 'كرتونة', 'symbol' => 'كرتونة', 'decimal_places' => 0, 'is_weight' => false],
            ['name' => 'زجاجة', 'symbol' => 'زجاجة', 'decimal_places' => 0, 'is_weight' => false],
            ['name' => 'برطمان', 'symbol' => 'برطمان', 'decimal_places' => 0, 'is_weight' => false],
            ['name' => 'لتر', 'symbol' => 'لتر', 'decimal_places' => 3, 'is_weight' => false],
            ['name' => 'ملليلتر', 'symbol' => 'مل', 'decimal_places' => 3, 'is_weight' => false],
        ];

        foreach ($units as $data) {
            Unit::updateOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }
}
