<?php

namespace App\Http\Controllers\Global\Products;

use App\Http\Controllers\Controller;
use App\Models\ArtworkTemplate;
use App\Models\ArtworkTemplateGroup;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ArtworkTemplateController extends Controller
{
    /**
     * Get grouped artwork templates for a specific category.
     * 
     * GET /api/templates/{category_id}
     */
    public function getByCategory(Request $request, $categoryId)
    {
        // 1. Find the category
        $category = Category::where('category_id', $categoryId)
            ->orWhere('id', $categoryId)
            ->firstOrFail();

        // 2. Get all relevant category IDs (the category + its children)
        $categoryIds = [$category->id];
        $children = $category->children()->pluck('id')->toArray();
        $allCategoryIds = array_merge($categoryIds, $children);

        // 3. Fetch groups with active templates for all these categories
        $groups = ArtworkTemplateGroup::with(['templates' => function ($query) {
            $query->where('active', true);
        }, 'category'])
            ->whereIn('category_id', $allCategoryIds)
            ->where('active', true)
            ->get();

        // 4. Format results hierarchically
        $subcategoriesMap = [];
        $totalTemplates = 0;

        foreach ($groups as $group) {
            // Determine if this group belongs to a subcategory
            $isSubCategory = $group->category_id != $category->id;
            $subCategoryId = $isSubCategory ? ($group->category->category_id ?? $group->category->id) : null;
            $subCategoryName = $isSubCategory ? $group->category->name : null;

            // Initialize the subcategory if it doesn't exist
            $subCatKey = $subCategoryId ?? 'parent';
            if (!isset($subcategoriesMap[$subCatKey])) {
                $subcategoriesMap[$subCatKey] = [
                    'sub_category_id' => $subCategoryId,
                    'sub_category_name' => $subCategoryName,
                    'groups' => []
                ];
            }

            $groupName = $group->group_value;

            // Initialize the group inside the subcategory if it doesn't exist
            if (!isset($subcategoriesMap[$subCatKey]['groups'][$groupName])) {
                $subcategoriesMap[$subCatKey]['groups'][$groupName] = [
                    'group' => $groupName,
                    'labels' => []
                ];
            }

            foreach ($group->templates as $template) {
                $totalTemplates++;

                $subcategoriesMap[$subCatKey]['groups'][$groupName]['labels'][] = [
                    'label' => $template->label,
                    'files' => array_map(function ($file) {
                        return [
                            'format' => $file['format'] ?? 'UNKNOWN',
                            'url' => $file['url']
                        ];
                    }, $template->files ?? [])
                ];
            }
        }

        // Filter out empty groups and reset group array keys
        foreach ($subcategoriesMap as $key => $subcat) {
            $filteredGroups = array_filter($subcat['groups'], function ($g) {
                return count($g['labels']) > 0;
            });
            $subcategoriesMap[$key]['groups'] = array_values($filteredGroups);
        }

        $formattedData = [
            [
                'subcategories' => array_values($subcategoriesMap),
                'category_id' => (string) ($category->category_id ?? $category->id),
                'category_name' => strtoupper($category->name)
            ]
        ];

        return response()->json([
            'message' => 'Templates retrieved successfully',
            'data' => [
                'meta' => [
                    'page' => 1,
                    'limit' => 10,
                    'total' => $totalTemplates
                ],
                'data' => $formattedData
            ]
        ]);
    }

    /**
     * Search for a specific template group based on category and options.
     * Useful for identifying which template set to show on the product page.
     * 
     * POST /api/templates/search
     * Payload: { "category_id": "...", "options": { "SIZE": "2x3.5", "SHAPE": "ROUNDED" } }
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required_without:sub_category_id',
            'sub_category_id' => 'required_without:category_id',
            'options' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $categoryId = $request->sub_category_id ?? $request->category_id;
        $searchOptions = $request->options ?? [];

        if (empty($searchOptions)) {
            // If options are sent as top-level keys like "options[SIZE]" in JSON body
            foreach ($request->all() as $key => $value) {
                if (preg_match('/^options\[(.*?)\]$/', $key, $matches)) {
                    $searchOptions[$matches[1]] = $value;
                }
            }
        }

        // 1. Find category
        $category = Category::where('category_id', $categoryId)
            ->orWhere('id', $categoryId)
            ->firstOrFail();

        Log::info('Category: ' . $category);
        // 2. Find matching group
        // A group is identified by its group_label and group_value matching the user's input
        $groupQuery = ArtworkTemplateGroup::where('category_id', $category->id)
            ->where('active', true);

        $groupQuery->where(function ($q) use ($searchOptions) {
            foreach ($searchOptions as $key => $value) {
                $q->orWhere(function ($sub) use ($key, $value) {
                    $sub->where('group_label', $key)
                        ->where('group_value', $value);
                })->orWhere(function ($sub) use ($key, $value) {
                    $sub->where('group_label', strtoupper($key))
                        ->where('group_value', $value);
                });
            }
        });

        $group = $groupQuery->first();

        Log::info('Group Found: ' . ($group ? $group->id : 'None'));

        if (!$group) {
            return response()->json([
                'success' => false,
                'message' => 'No matching template group found for these options.'
            ], 404);
        }

        // 3. Filter Variations within the group
        $templates = $group->templates()->where('active', true)->get();
        Log::info('Templates in Group: ' . $templates->count());

        $variations = $templates->filter(function ($template) use ($searchOptions) {
            $templateOpts = $template->options ?? [];
            Log::info('Checking Template: ' . $template->id . ' | Label: ' . $template->label . ' | Opts: ' . json_encode($templateOpts));

            if (empty($templateOpts)) {
                Log::info('Template ' . $template->id . ' has no filters, including.');
                return true;
            }

            foreach ($templateOpts as $key => $targetValue) {
                $userValue = $searchOptions[$key] ?? $searchOptions[strtoupper($key)] ?? null;

                if ($userValue === null) {
                    Log::info('Option ' . $key . ' not in search, skipping filter for this key.');
                    continue;
                }

                Log::info('Matching ' . $key . ': Template expects "' . json_encode($targetValue) . '", User sent "' . $userValue . '"');

                // Matching Logic:
                if (is_array($targetValue)) {
                    if (!in_array($userValue, $targetValue)) {
                        Log::info('FAIL: User value ' . $userValue . ' NOT in template array ' . json_encode($targetValue));
                        return false;
                    }
                } else {
                    if ($targetValue != $userValue) {
                        Log::info('FAIL: Exact match failed. Template: ' . $targetValue . ' != User: ' . $userValue);
                        return false;
                    }
                }
            }
            Log::info('PASS: Template matched all conditions.');
            return true;
        })->values();

        // 4. Format variations into the requested flat structure
        $data = $variations->flatMap(function ($template) use ($category, $group) {
            // Merge Group options and Template options, ensuring keys are uppercase
            $mergedOptions = [];
            $mergedOptions[strtoupper($group->group_label)] = $group->group_value;

            if (isset($template->options) && is_array($template->options)) {
                foreach ($template->options as $k => $v) {
                    $mergedOptions[strtoupper($k)] = $v;
                }
            }

            $baseData = [
                'category_id' => (string) $category->id,
                'category_name' => strtoupper($category->name),
                'sub_category_id' => null,
                'sub_category_name' => null,
                'group' => $group->group_value,
                'options' => $mergedOptions,
                'files' => array_map(function ($file) use ($template) {
                    return [
                        '_id' => uniqid(),
                        'file_name' => basename($file['url']),
                        'format' => $file['format'] ?? 'UNKNOWN',
                        'url' => $file['url'],
                        'createdAt' => $template->created_at->toISOString(),
                        'updatedAt' => $template->updated_at->toISOString(),
                        '__v' => 0
                    ];
                }, $template->files ?? []),
                'createdAt' => $template->created_at->toISOString(),
                'updatedAt' => $template->updated_at->toISOString(),
                '__v' => 0
            ];

            // If template is BOTH sides, return two distinct entries
            if ($template->side === 'BOTH') {
                return [
                    array_merge($baseData, [
                        '_id' => $template->id . '_front', // Unique ID for front
                        'label' => $template->label . ' (FRONT)',
                    ]),
                    array_merge($baseData, [
                        '_id' => $template->id . '_back', // Unique ID for back
                        'label' => $template->label . ' (BACK)',
                    ])
                ];
            }

            // Otherwise return single entry
            return [
                array_merge($baseData, [
                    '_id' => (string) $template->id,
                    'label' => $template->label,
                ])
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
