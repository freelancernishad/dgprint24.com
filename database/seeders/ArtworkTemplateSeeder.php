<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ArtworkTemplate;
use App\Models\ArtworkTemplateGroup;
use Illuminate\Database\Seeder;

class ArtworkTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('name', 'LIKE', '%Business Card%')->first();

        if (!$category) {
            $category = Category::first();
        }

        if (!$category) return;

        // 1. Create a Standard Size Group
        $standardGroup = ArtworkTemplateGroup::create([
            'category_id' => $category->id,
            'group_label' => 'SIZE',
            'group_value' => '2″ x 3.5″',
            'sides_count' => 2, // Required Front and Back
            'options' => ['SIZE' => '2″ x 3.5″']
        ]);

        // Attach variations to this group
        $standardTemplates = [
            [
                'side' => 'FRONT',
                'label' => 'Standard Horizontal (Front)',
                'options' => ['ORIENTATION' => 'HORIZONTAL', 'SHAPE' => 'RECTANGLE'],
                'files' => [['format' => 'EPS', 'url' => '...'], ['format' => 'PDF', 'url' => '...']]
            ],
            [
                'side' => 'BACK',
                'label' => 'Standard Horizontal (Back)',
                'options' => ['ORIENTATION' => 'HORIZONTAL', 'SHAPE' => 'RECTANGLE'],
                'files' => [['format' => 'EPS', 'url' => '...']]
            ],
            [
                'side' => 'BOTH',
                'label' => 'Rounded Corner Layout',
                'options' => ['SHAPE' => 'ROUNDED CORNER'],
                'files' => [['format' => 'EPS', 'url' => '...']]
            ]
        ];

        foreach ($standardTemplates as $t) {
            $standardGroup->templates()->create($t);
        }

        // 2. Create a Mini Size Group
        $miniGroup = ArtworkTemplateGroup::create([
            'category_id' => $category->id,
            'group_label' => 'SIZE',
            'group_value' => '1.5″ x 3.5″',
            'sides_count' => 1,
            'options' => ['SIZE' => '1.5″ x 3.5″']
        ]);

        $miniGroup->templates()->create([
            'side' => 'FRONT',
            'label' => 'Mini Card Horizontal',
            'options' => ['ORIENTATION' => 'HORIZONTAL'],
            'files' => [['format' => 'PDF', 'url' => '...']]
        ]);
    }
}
