<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

use App\Models\Category;
use App\Http\Controllers\Global\Products\ArtworkTemplateController;
use Illuminate\Http\Request;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// 1. Find a category that has templates
$category = Category::whereHas('templates')->first();

if (!$category) {
    echo "No category with templates found. Please run the seeder first.\n";
    exit;
}

echo "Testing Category: {$category->name} (Grouped by: {$category->template_group_by})\n";

// 2. Mock request
$controller = new ArtworkTemplateController();
$request = Request::create("/api/templates/{$category->id}", 'GET');

// 3. Get response
$response = $controller->getByCategory($request, $category->id);
$data = json_decode($response->getContent(), true);

// 4. Output results
echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
echo "Grouped By Key: " . $data['group_by_key'] . "\n";
echo "Number of size groups: " . count($data['templates']) . "\n";

foreach ($data['templates'] as $group) {
    echo " - Group: {$group['group_name']} (Total Variations: " . count($group['variations']) . ")\n";
    foreach ($group['variations'] as $v) {
        echo "   * {$v['label']} (SHAPE: " . ($v['options']['SHAPE'] ?? 'N/A') . ")\n";
    }
}
