<?php

namespace App\Http\Controllers\Admin\Products\ProductEdit;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ProductImageController extends Controller
{
    // GET /admin/products/{product}/images
    public function getImages(Product $product)
    {
        $images = $product->images()->orderBy('sort_order')->get();
        return response()->json(['images' => $images], Response::HTTP_OK);
    }

    // POST /admin/products/{product}/images
    public function addImage(Request $request, Product $product)
    {
        $v = Validator::make($request->all(), [
            'image_url' => 'required|url',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $img = $product->images()->create([
            'image_url' => $request->input('image_url'),
            'sort_order' => $request->input('sort_order', 0),
        ]);

        return response()->json(['message' => 'Image added', 'image' => $img], Response::HTTP_CREATED);
    }

    // PUT /admin/products/{product}/images/sync
    public function syncImages(Request $request, Product $product)
    {
        $v = Validator::make($request->all(), [
            'images' => 'required|array',
            'images.*.id' => 'nullable|integer|exists:product_images,id',
            'images.*.image_url' => 'required|url',
            'images.*.sort_order' => 'nullable|integer|min:0',
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $images = $v->validated()['images'];

        DB::beginTransaction();
        try {
            $incomingIds = collect($images)->pluck('id')->filter()->values()->all();

            // delete orphans
            $product->images()->whereNotIn('id', $incomingIds)->delete();

            foreach ($images as $img) {
                if (!empty($img['id'])) {
                    $product->images()->where('id', $img['id'])->update([
                        'image_url' => $img['image_url'],
                        'sort_order' => $img['sort_order'] ?? 0,
                    ]);
                } else {
                    $product->images()->create([
                        'image_url' => $img['image_url'],
                        'sort_order' => $img['sort_order'] ?? 0,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Images synced', 'images' => $product->images()->orderBy('sort_order')->get()], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('syncImages: '.$e->getMessage());
            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /admin/products/{product}/images/{image}
    public function deleteImage(Product $product, ProductImage $image)
    {
        // ensure image belongs to product
        if ($image->product_id !== $product->id) {
            return response()->json(['message' => 'Image does not belong to product'], Response::HTTP_FORBIDDEN);
        }

        $image->delete();
        return response()->json(['message' => 'Image deleted'], 200);
    }
}
