<?php

namespace App\Http\Controllers\Admin\Products;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\ProductPriceRule;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Pricing\ProductPriceRuleService;

class ProductPriceRuleController extends Controller
{
    /**
     * List rules
     */
    public function index()
    {
        return response()->json(
            ProductPriceRule::latest()->paginate(20)
        );
    }

    /**
     * Store new rule
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'nullable|exists:products,product_id',
            'type' => ['required', Rule::in(['discount', 'price_add'])],
            'value_type' => ['required', Rule::in(['flat', 'percentage'])],
            'value' => 'required|numeric|min:0',
            'active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $rule = ProductPriceRule::create($data);

        return response()->json([
            'message' => 'Price rule created successfully',
            'data' => $rule
        ], 201);
    }

    /**
     * Show single rule
     */
    public function show(ProductPriceRule $productPriceRule)
    {
        return response()->json($productPriceRule);
    }

    /**
     * Update rule
     */
    public function update(Request $request, ProductPriceRule $productPriceRule)
    {
        $data = $request->validate([
            'product_id' => 'nullable|exists:products,product_id',
            'type' => ['required', Rule::in(['discount', 'price_add'])],
            'value_type' => ['required', Rule::in(['flat', 'percentage'])],
            'value' => 'required|numeric|min:0',
            'active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $productPriceRule->update($data);

        return response()->json([
            'message' => 'Price rule updated successfully',
            'data' => $productPriceRule
        ]);
    }

    /**
     * Delete rule
     */
    public function destroy(ProductPriceRule $productPriceRule)
    {
        $productPriceRule->delete();

        return response()->json([
            'message' => 'Price rule deleted successfully'
        ]);
    }

public function activate($id)
{
    $rule = ProductPriceRule::findOrFail($id);

    DB::transaction(function () use ($rule) {

        // যদি rule টা already active থাকে
        if ($rule->active) {

            // 👉 সব rule deactivate করে দাও
            ProductPriceRule::where('active', true)
                ->update(['active' => false]);

        } else {

            // 👉 আগে সব deactivate
            ProductPriceRule::where('active', true)
                ->update(['active' => false]);

            // 👉 তারপর এই rule activate
            $rule->update(['active' => true]);
        }
    });

    return response()->json([
        'message' => $rule->active
            ? 'All price rules deactivated'
            : 'Price rule activated successfully'
    ]);
}

public function calculate(
    Request $request,
    ProductPriceRuleService $pricingService
) {
    $data = $request->validate([
        'base_price' => 'required|numeric|min:0',
        'product_id' => 'nullable|exists:products,product_id',
    ]);

    $finalPrice = $pricingService->calculate(
        $data['base_price'],
        $data['product_id'] ?? null
    );

    return response()->json([
        'base_price' => (float) $data['base_price'],
        'final_price' => (float) $finalPrice,
        'difference' => round($finalPrice - $data['base_price'], 2),
    ]);
}

}
