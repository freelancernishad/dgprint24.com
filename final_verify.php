<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

use App\Models\Category;
use App\Models\ArtworkTemplateGroup;
use App\Http\Controllers\Global\Products\ArtworkTemplateController;
use Illuminate\Http\Request;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// 1. Find a category with groups
$category = Category::whereHas('templateGroups')->first();

if (!$category) {
    echo "No category with template groups found.\n";
    exit;
}

echo "Category: {$category->name}\n";

// 2. Test getByCategory
$controller = new ArtworkTemplateController();
$request = Request::create("/api/templates/{$category->id}", 'GET');
$response = $controller->getByCategory($request, $category->id);
$data = json_decode($response->getContent(), true);

echo "Groups found: " . count($data['groups']) . "\n";
foreach ($data['groups'] as $g) {
    echo " - Group: {$g['group_label']} = {$g['group_value']} (Sides: {$g['sides_required']})\n";
    foreach ($g['variations'] as $v) {
        echo "   * {$v['label']} (Side: {$v['side']})\n";
    }
}

// 3. Test Search
echo "\nTesting Search for Size '2″ x 3.5″'...\n";
$searchRequest = Request::create("/api/templates/search", 'POST', [
    'category_id' => $category->id,
    'options' => ['SIZE' => '2″ x 3.5″']
]);
$searchResponse = $controller->search($searchRequest);
$searchData = json_decode($searchResponse->getContent(), true);

if ($searchData['success']) {
    echo "Search Success! Group Found: {$searchData['group_value']}\n";
    echo "Variations returned: " . count($searchData['variations']) . "\n";
} else {
    echo "Search Failed: " . ($searchData['message'] ?? 'Unknown Error') . "\n";
}
