<?php

namespace App\Http\Controllers\Admin\Products\ProductEdit;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\PriceConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\Controller;
use App\Models\PriceConfigurationOption;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class PriceConfigController extends Controller
{
    // GET /admin/products/{product}/price-configs
    public function getPriceConfigs(Product $product)
    {
        $configs = $product->priceConfigurations()->with(['shippings', 'turnarounds', 'optionsRel'])->get();
        return response()->json(['priceConfigs' => $configs], Response::HTTP_OK);
    }

    // POST /admin/products/{product}/price-configs
    public function addPriceConfig(Request $request, Product $product)
    {
        $v = Validator::make($request->all(), [
            'runsize' => 'required|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|max:100',
            'options' => 'nullable|array',
            'shippings' => 'nullable|array',
            'turnarounds' => 'nullable|array',
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $cfg = $product->priceConfigurations()->create([
                'runsize' => $request->input('runsize'),
                'price' => $request->input('price', 0),
                'discount' => $request->input('discount', 0),
                'options' => $request->input('options', []),
            ]);

            // options relational
            if ($request->filled('options') && is_array($request->input('options'))) {
                $pairs = [];
                foreach ($request->input('options') as $k => $v) {
                    $pairs[] = ['key' => $k, 'value' => is_array($v) ? json_encode($v) : (string)$v];
                }
                $cfg->optionsRel()->createMany($pairs);
            }

            // shippings
            if ($request->filled('shippings') && is_array($request->input('shippings'))) {
                foreach ($request->input('shippings') as $s) {
                    $cfg->shippings()->create($s);
                }
            }

            // turnarounds
            if ($request->filled('turnarounds') && is_array($request->input('turnarounds'))) {
                foreach ($request->input('turnarounds') as $t) {
                    $cfg->turnarounds()->create($t);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Price config added', 'priceConfig' => $cfg->fresh()], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('addPriceConfig: '.$e->getMessage());
            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PUT /admin/products/{product}/price-configs/sync
    public function syncPriceConfigs(Request $request, Product $product)
    {
        $v = Validator::make($request->all(), [
            'priceConfigs' => 'required|array',
            'priceConfigs.*.id' => 'nullable|integer|exists:price_configurations,id',
            'priceConfigs.*.runsize' => 'required|integer|min:1',
            'priceConfigs.*.price' => 'nullable|numeric|min:0',
            'priceConfigs.*.discount' => 'nullable|numeric|min:0|max:100',
            'priceConfigs.*.options' => 'nullable|array',
            'priceConfigs.*.shippings' => 'nullable|array',
            'priceConfigs.*.turnarounds' => 'nullable|array',
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $configs = $v->validated()['priceConfigs'];

        DB::beginTransaction();
        try {
            $incomingIds = collect($configs)->pluck('id')->filter()->values()->all();

            // delete missing
            $product->priceConfigurations()->whereNotIn('id', $incomingIds)->delete();

            foreach ($configs as $c) {
                if (!empty($c['id'])) {
                    $pc = $product->priceConfigurations()->where('id', $c['id'])->first();
                    if (!$pc) throw new \Exception("Price config id {$c['id']} not found for product.");
                    $pc->update([
                        'runsize' => $c['runsize'],
                        'price' => $c['price'] ?? 0,
                        'discount' => $c['discount'] ?? 0,
                        'options' => $c['options'] ?? [],
                    ]);
                } else {
                    $pc = $product->priceConfigurations()->create([
                        'runsize' => $c['runsize'],
                        'price' => $c['price'] ?? 0,
                        'discount' => $c['discount'] ?? 0,
                        'options' => $c['options'] ?? [],
                    ]);
                }

                // optionsRel replacement
                if (isset($c['options']) && is_array($c['options'])) {
                    $pc->optionsRel()->delete();
                    $pairs = [];
                    foreach ($c['options'] as $k => $v) {
                        $pairs[] = ['key' => $k, 'value' => is_array($v) ? json_encode($v) : (string)$v];
                    }
                    if (!empty($pairs)) $pc->optionsRel()->createMany($pairs);
                }

                // shippings sync (replace)
                if (isset($c['shippings']) && is_array($c['shippings'])) {
                    $pc->shippings()->delete();
                    foreach ($c['shippings'] as $s) {
                        $pc->shippings()->create($s);
                    }
                }

                // turnarounds sync (replace)
                if (isset($c['turnarounds']) && is_array($c['turnarounds'])) {
                    $pc->turnarounds()->delete();
                    foreach ($c['turnarounds'] as $t) {
                        $pc->turnarounds()->create($t);
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'Price configs synced', 'priceConfigs' => $product->priceConfigurations()->with(['shippings','turnarounds','optionsRel'])->get()], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('syncPriceConfigs: '.$e->getMessage());
            return response()->json(['message'=>'Internal server error','error'=>$e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    /**
 * PATCH /admin/products/{product}/price-configs/{config}
 * Update a single PriceConfiguration and replace its relational children (shippings, turnarounds).
 * NOTE: updating 'options' via this endpoint is NOT ALLOWED and will be rejected with 403.
 *
 * Expected body (any of these fields optional; send only what you want to change):
 * {
 *   "runsize": 100,
 *   "price": 1200,
 *   "discount": 5,
 *   // "options": { ... }  <-- NOT ALLOWED here
 *   "shippings": [ ... ],
 *   "turnarounds": [ ... ]
 * }
 */
public function updatePriceConfig(Request $request, Product $product, PriceConfiguration $config)
{
    // ensure config belongs to product
    if ($config->product_id != $product->id) {
        return response()->json(['message' => 'Config does not belong to product'], Response::HTTP_FORBIDDEN);
    }

    // If client tries to update 'options' here, block it.
    // if ($request->has('options')) {
    //     return response()->json([
    //         'message' => 'Updating "options" is not allowed via this endpoint. Use the dedicated options endpoint.'
    //     ], Response::HTTP_FORBIDDEN);
    // }

    $rules = [
        'runsize' => 'nullable|integer|min:1',
        'price' => 'nullable|numeric|min:0',
        'discount' => 'nullable|numeric|min:0|max:100',
        'shippings' => 'nullable|array',
        'shippings.*.shippingLabel' => 'required_with:shippings|string',
        'shippings.*.shippingValue' => 'required_with:shippings',
        'shippings.*.price' => 'nullable|numeric|min:0',
        'shippings.*.note' => 'nullable|string',
        'turnarounds' => 'nullable|array',
        'turnarounds.*.turnaroundLabel' => 'required_with:turnarounds|string',
        'turnarounds.*.turnaroundValue' => 'required_with:turnarounds',
        'turnarounds.*.price' => 'nullable|numeric|min:0',
        'turnarounds.*.note' => 'nullable|string',
    ];

    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $data = $validator->validated();

    DB::beginTransaction();
    try {
        // Update main fields if provided
        $updateData = [];
        if (array_key_exists('runsize', $data)) $updateData['runsize'] = $data['runsize'];
        if (array_key_exists('price', $data)) $updateData['price'] = $data['price'] ?? 0;
        if (array_key_exists('discount', $data)) $updateData['discount'] = $data['discount'] ?? 0;

        if (!empty($updateData)) {
            $config->update($updateData);
        }

        // Note: options update intentionally omitted / forbidden.

        // Replace shippings if provided
        if (array_key_exists('shippings', $data) && is_array($data['shippings'])) {
            $config->shippings()->delete();
            foreach ($data['shippings'] as $s) {
                $config->shippings()->create([
                    'shippingLabel' => $s['shippingLabel'],
                    'shippingValue' => $s['shippingValue'],
                    'price' => $s['price'] ?? 0,
                    'note' => $s['note'] ?? null,
                ]);
            }
        }

        // Replace turnarounds if provided
        if (array_key_exists('turnarounds', $data) && is_array($data['turnarounds'])) {
            $config->turnarounds()->delete();
            foreach ($data['turnarounds'] as $t) {
                $config->turnarounds()->create([
                    'turnaroundLabel' => $t['turnaroundLabel'],
                    'turnaroundValue' => $t['turnaroundValue'],
                    'price' => $t['price'] ?? 0,
                    'note' => $t['note'] ?? null,
                ]);
            }
        }

        DB::commit();

        $fresh = $config->fresh()->load(['shippings', 'turnarounds', 'optionsRel']);
        return response()->json(['message' => 'Price configuration updated (options not modified)', 'priceConfig' => $fresh], Response::HTTP_OK);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('updatePriceConfig: ' . $e->getMessage());
        return response()->json(['message' => 'Internal server error', 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}





    // DELETE /admin/products/{product}/price-configs/{config}
    public function deletePriceConfig(Product $product, PriceConfiguration $config)
    {
        if ($config->product_id !== $product->id) {
            return response()->json(['message'=>'Config not belong to product'], Response::HTTP_FORBIDDEN);
        }
        $config->delete();
        return response()->json(['message'=>'Price config deleted'], Response::HTTP_OK);
    }
}
