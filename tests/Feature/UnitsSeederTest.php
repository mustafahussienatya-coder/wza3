<?php

use App\Modules\Units\Models\Unit;
use Database\Seeders\UnitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the twelve confirmed units', function () {
    $this->seed(UnitsSeeder::class);

    expect(Unit::count())->toBe(12);
});

it('seeds three weight units with the right decimals', function () {
    $this->seed(UnitsSeeder::class);

    expect(Unit::where('is_weight', true)->count())->toBe(3);

    expect(Unit::where('name', 'جرام')->first())
        ->decimal_places->toBe(0)
        ->is_weight->toBeTrue();

    expect(Unit::where('name', 'كيلوجرام')->first())
        ->decimal_places->toBe(3)
        ->is_weight->toBeTrue();

    expect(Unit::where('name', 'طن')->first())
        ->decimal_places->toBe(3)
        ->is_weight->toBeTrue();
});

it('seeds liquid and quantity units with the right decimals', function () {
    $this->seed(UnitsSeeder::class);

    expect(Unit::where('name', 'لتر')->first())
        ->decimal_places->toBe(3)
        ->is_weight->toBeFalse();

    expect(Unit::where('name', 'ملليلتر')->first())
        ->decimal_places->toBe(3)
        ->symbol->toBe('مل')
        ->is_weight->toBeFalse();

    expect(Unit::where('name', 'برطمان')->first())
        ->decimal_places->toBe(0)
        ->is_weight->toBeFalse();
});

it('marks all seeded units as active', function () {
    $this->seed(UnitsSeeder::class);

    expect(Unit::where('is_active', false)->count())->toBe(0);
});

it('is idempotent when run again', function () {
    $this->seed(UnitsSeeder::class);
    $this->seed(UnitsSeeder::class);

    expect(Unit::count())->toBe(12);
});
