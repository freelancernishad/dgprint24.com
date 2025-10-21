<?php

// app/Http/Controllers/UsaMarry/Api/Admin/Plans/FeatureController.php
namespace App\Http\Controllers\Admin\Plans;

use Illuminate\Http\Request;
use App\Models\Plan\PlanFeature;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class FeatureController extends Controller
{
    public function index()
    {
        return response()->json(PlanFeature::all());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|unique:plan_features',
            'title_template' => 'required|string',
            'unit' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $feature = PlanFeature::create($validated);
        return response()->json($feature, 201);
    }

    public function show($id)
    {
        return response()->json(PlanFeature::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $feature = PlanFeature::findOrFail($id);

        $validated = $request->validate([
            'key' => 'sometimes|string|unique:features,key,' . $feature->id,
            'title_template' => 'sometimes|string',
            'unit' => 'nullable|string',
        ]);

        $feature->update($validated);
        return response()->json($feature);
    }

    public function destroy($id)
    {
        $feature = PlanFeature::findOrFail($id);
        $feature->delete();

        return response()->json(['message' => 'Feature deleted']);
    }


   public function templateInputList()
{
    $features = PlanFeature::all();

    $response = $features->map(function ($feature) {
        preg_match_all('/:(\w+)/', $feature->title_template, $matches);
        $placeholders = $matches[1] ?? [];

        $inputs = collect($placeholders)->map(function ($key) use ($feature) {
            return [
                'name' => $key,
                'type' => 'text',
                'label' => ucfirst(str_replace('_', ' ', $key)),
                'placeholder' => "Enter the " . str_replace('_', ' ', $key) . " for " . str_replace('_', ' ', $feature->key),
            ];
        })->toArray();

        return [
            'key' => $feature->key,
            'title_template' => $feature->title_template,
            'inputs' => $inputs
        ];
    });

    return response()->json($response);
}






}
