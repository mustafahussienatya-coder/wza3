<?php

use App\Modules\Categories\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds hierarchical categories for the herbal store', function () {
    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe(21);
    expect(Category::whereNull('parent_id')->count())->toBe(5);
    expect(Category::whereNotNull('parent_id')->count())->toBe(16);
});

it('is idempotent when run again', function () {
    $this->seed(CategorySeeder::class);
    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe(21);
    expect(Category::whereNull('parent_id')->count())->toBe(5);
});

it('links children to the correct parent', function () {
    $this->seed(CategorySeeder::class);

    $herbs = Category::where('code', 'HERBS_SPICES')->first();
    expect($herbs)->not->toBeNull();

    $spices = Category::where('code', 'HERBS_SPICES_SPICES')->first();
    expect($spices->parent_id)->toBe($herbs->id);
});
