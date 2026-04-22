<?php

namespace App\Http\Controllers\Admin\Products;

use App\Models\Category;
use App\Models\ArtworkTemplate;
use App\Models\ArtworkTemplateGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminArtworkTemplateController extends Controller
{
    /**
     * List all groups for a category.
     */
    public function indexGroups($categoryId)
    {
        $groups = ArtworkTemplateGroup::with('templates')
            ->where('category_id', $categoryId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $groups // Keep it consistent with the user's JSON wrapper
        ]);
    }

    /**
     * Show a single template group.
     */
    public function showGroup($id)
    {
        $group = ArtworkTemplateGroup::with('templates')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $group
        ]);
    }

    /**
     * Store a new template group.
     */
    public function storeGroup(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'group_label' => 'required|string',
            'group_value' => 'required|string',
            'sides_count' => 'required|integer|min:1',
            'options' => 'nullable|array',
            'active' => 'boolean'
        ]);

        $group = ArtworkTemplateGroup::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template group created successfully.',
            'group' => $group
        ], 201);
    }

    /**
     * Update a template group.
     */
    public function updateGroup(Request $request, $id)
    {
        $group = ArtworkTemplateGroup::findOrFail($id);

        $validated = $request->validate([
            'group_label' => 'sometimes|string',
            'group_value' => 'sometimes|string',
            'sides_count' => 'sometimes|integer|min:1',
            'options' => 'nullable|array',
            'active' => 'boolean'
        ]);

        $group->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template group updated successfully.',
            'group' => $group
        ]);
    }

    /**
     * Delete a group and its templates.
     */
    public function destroyGroup($id)
    {
        $group = ArtworkTemplateGroup::findOrFail($id);
        $group->delete(); // Cascades to templates due to DB foreign key onDelete('cascade')

        return response()->json([
            'success' => true,
            'message' => 'Template group and associated templates deleted successfully.'
        ]);
    }

    /**
     * Store one or more new variation templates under a group.
     */
    public function storeTemplate(Request $request, $groupId)
    {
        $group = ArtworkTemplateGroup::findOrFail($groupId);

        // Check if input is a list of variations or a single one
        $isBulk = isset($request->variations) && is_array($request->variations);
        $data = $isBulk ? $request->variations : [$request->all()];

        $results = [];
        foreach ($data as $item) {
            $validated = validator($item, [
                'side' => 'required|string|in:FRONT,BACK,BOTH',
                'label' => 'required|string',
                'options' => 'nullable|array',
                'files' => 'required|array',
                'files.*.format' => 'required|string',
                'files.*.url' => 'required|string',
                'active' => 'boolean'
            ])->validate();

            $results[] = $group->templates()->create($validated);
        }

        return response()->json([
            'success' => true,
            'message' => count($results) . ' Template variation(s) created successfully.',
            'templates' => $results
        ], 201);
    }

    /**
     * Update a template variation.
     */
    public function updateTemplate(Request $request, $id)
    {
        $template = ArtworkTemplate::findOrFail($id);

        $validated = $request->validate([
            'side' => 'sometimes|string|in:FRONT,BACK,BOTH',
            'label' => 'sometimes|string',
            'options' => 'nullable|array',
            'files' => 'sometimes|array',
            'active' => 'boolean'
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template variation updated successfully.',
            'template' => $template
        ]);
    }

    /**
     * Show a single template variation.
     */
    public function showTemplate($id)
    {
        $template = ArtworkTemplate::with('group.category')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $template
        ]);
    }

    /**
     * Delete a specific template variation.
     */
    public function destroyTemplate($id)
    {
        $template = ArtworkTemplate::findOrFail($id);
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template variation deleted successfully.'
        ]);
    }
}
