<?php

namespace App\Http\Controllers\Admin\Products;

use App\Http\Controllers\Controller;
use App\Models\ProductPriceRule;
use App\Services\Pricing\ProductPriceRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductPriceRuleController extends Controller
{
    public function index()
    {
        return response()->json(
            ProductPriceRule::latest()->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|exists:products,product_id',
            'label' => 'nullable|string|max:255', // ✅ added
            'type' => ['required', Rule::in(['discount', 'price_add'])],
            'value_type' => ['required', Rule::in(['flat', 'percentage'])],
            'value' => 'required|numeric|min:0',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $rule = ProductPriceRule::create($validator->validated());

        return response()->json([
            'message' => 'Price rule created successfully',
            'data' => $rule,
        ], 201);
    }

    public function show(ProductPriceRule $productPriceRule)
    {
        return response()->json($productPriceRule);
    }

    public function update(Request $request, ProductPriceRule $productPriceRule)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|exists:products,product_id',
            'label' => 'nullable|string|max:255', // ✅ added
            'type' => ['required', Rule::in(['discount', 'price_add'])],
            'value_type' => ['required', Rule::in(['flat', 'percentage'])],
            'value' => 'required|numeric|min:0',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $productPriceRule->update($validator->validated());

        return response()->json([
            'message' => 'Price rule updated successfully',
            'data' => $productPriceRule,
        ]);
    }

    public function destroy(ProductPriceRule $productPriceRule)
    {
        $productPriceRule->delete();

        return response()->json([
            'message' => 'Price rule deleted successfully',
        ]);
    }

    /**
     * Toggle activate / deactivate
     */
    public function activate($id)
    {
        $rule = ProductPriceRule::findOrFail($id);

        DB::transaction(function () use ($rule) {

            ProductPriceRule::where('active', true)
                ->update(['active' => false]);

            if (! $rule->active) {
                $rule->update(['active' => true]);
            }
        });

        return response()->json([
            'message' => $rule->active
                ? 'All price rules deactivated'
                : 'Price rule activated successfully',
        ]);
    }

    public function calculate(
        Request $request,
        ProductPriceRuleService $pricingService
    ) {
        $validator = Validator::make($request->all(), [
            'base_price' => 'required|numeric|min:0',
            'product_id' => 'nullable|exists:products,product_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

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
