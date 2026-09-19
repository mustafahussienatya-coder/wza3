<?php

namespace Database\Seeders;

use App\Modules\Categories\Enums\CategoryStatus;
use App\Modules\Categories\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $parents = [
            [
                'name' => 'أعشاب وتوابل',
                'code' => 'HERBS_SPICES',
                'description' => 'توابل وأعشاب مجففة وطبية تستخدم في المطبخ والعلاج.',
                'children' => [
                    ['name' => 'توابل', 'code' => 'HERBS_SPICES_SPICES', 'description' => 'توابل مطحونة وحبوب مثل الكمون والكزبرة والفلفل.'],
                    ['name' => 'أعشاب مجففة', 'code' => 'HERBS_SPICES_DRIED_HERBS', 'description' => 'أعشاب مجففة للطهي مثل النعناع والزعتر والميرمية.'],
                    ['name' => 'أعشاب طبية', 'code' => 'HERBS_SPICES_MEDICINAL_HERBS', 'description' => 'أعشاب للأغراض الطبية مثل البابونج والشمر واليانسون.'],
                    ['name' => 'خلطات توابل', 'code' => 'HERBS_SPICES_BLENDS', 'description' => 'خلطات توابل جاهزة مثل بهارات الشاورما والكاري.'],
                ],
            ],
            [
                'name' => 'مكسرات ومجففات',
                'code' => 'NUTS_DRIED_FRUITS',
                'description' => 'مكسرات وفواكه مجففة وتسالي صحية.',
                'children' => [
                    ['name' => 'مكسرات', 'code' => 'NUTS_DRIED_FRUITS_NUTS', 'description' => 'مكسرات نيئة ومحمصة مثل اللوز والفستق والكاجو.'],
                    ['name' => 'فواكه مجففة', 'code' => 'NUTS_DRIED_FRUITS_DRIED', 'description' => 'فواكه مجففة مثل المشمش والتين والزبيب.'],
                    ['name' => 'تسالي', 'code' => 'NUTS_DRIED_FRUITS_SNACKS', 'description' => 'تسالي ومقرمشات خفيفة.'],
                ],
            ],
            [
                'name' => 'حبوب وبذور',
                'code' => 'GRAINS_SEEDS',
                'description' => 'حبوب وبذور وبقوليات طبيعية.',
                'children' => [
                    ['name' => 'حبوب', 'code' => 'GRAINS_SEEDS_GRAINS', 'description' => 'حبوب كاملة مثل القمح والشعير والشوفان.'],
                    ['name' => 'بذور', 'code' => 'GRAINS_SEEDS_SEEDS', 'description' => 'بذور مثل السمسم والكتان والشيا.'],
                    ['name' => 'بقوليات', 'code' => 'GRAINS_SEEDS_LEGUMES', 'description' => 'بقوليات مثل العدس والفول والحمص.'],
                ],
            ],
            [
                'name' => 'منتجات طبيعية',
                'code' => 'NATURAL_PRODUCTS',
                'description' => 'منتجات طبيعية صحية وجمالية.',
                'children' => [
                    ['name' => 'عسل', 'code' => 'NATURAL_PRODUCTS_HONEY', 'description' => 'عسل طبيعي ومشتقاته.'],
                    ['name' => 'زيوت طبيعية', 'code' => 'NATURAL_PRODUCTS_OILS', 'description' => 'زيوت طبيعية مثل زيت اللوز وزيت النعناع.'],
                    ['name' => 'منتجات طبيعية أخرى', 'code' => 'NATURAL_PRODUCTS_OTHER', 'description' => 'منتجات طبيعية متنوعة أخرى.'],
                ],
            ],
            [
                'name' => 'شاي ومشروبات',
                'code' => 'TEA_BEVERAGES',
                'description' => 'شاي وأعشاب للمشروبات ومشروبات طبيعية.',
                'children' => [
                    ['name' => 'شاي', 'code' => 'TEA_BEVERAGES_TEA', 'description' => 'أنواع الشاي مثل الأخضر والأسود والكركديه.'],
                    ['name' => 'أعشاب للمشروبات', 'code' => 'TEA_BEVERAGES_HERBAL', 'description' => 'أعشاب تُستخدم كمشروبات ساخنة.'],
                    ['name' => 'مشروبات طبيعية', 'code' => 'TEA_BEVERAGES_NATURAL', 'description' => 'مشروبات طبيعية جاهزة أو جافة.'],
                ],
            ],
        ];

        foreach ($parents as $parentData) {
            $children = $parentData['children'];
            unset($parentData['children']);

            $parent = $this->upsertCategory($parentData);

            foreach ($children as $childData) {
                $childData['parent_id'] = $parent->id;
                $this->upsertCategory($childData);
            }
        }
    }

    private function upsertCategory(array $data): Category
    {
        $category = Category::query()
            ->where('code', $data['code'])
            ->orWhere('name', $data['name'])
            ->first();

        if ($category === null) {
            return Category::create([
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'status' => CategoryStatus::ACTIVE,
            ]);
        }

        $category->update([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? $category->description,
            'parent_id' => $data['parent_id'] ?? $category->parent_id,
            'status' => CategoryStatus::ACTIVE,
        ]);

        return $category->fresh();
    }
}
