<?php

namespace App\Http\Controllers\Admin\Products\ProductEdit;

use App\Models\Faq;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ProductFaqController extends Controller
{
    // GET /admin/products/{product}/faqs
    public function getFaqs(Product $product)
    {
        $faqs = $product->faqs()->orderBy('sort_order')->get();
        return response()->json(['faqs' => $faqs], Response::HTTP_OK);
    }

    // POST /admin/products/{product}/faqs
    public function addFaq(Request $request, Product $product)
    {
        $v = Validator::make($request->all(), [
            'question' => 'required|string|max:1000',
            'answer' => 'nullable|string|max:4000',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $faq = $product->faqs()->create($v->validated());

        return response()->json(['message' => 'FAQ added', 'faq' => $faq], Response::HTTP_CREATED);
    }

    // PUT /admin/products/{product}/faqs/sync
    public function syncFaqs(Request $request, Product $product)
    {
        $v = Validator::make($request->all(), [
            'faqs' => 'required|array',
            'faqs.*.id' => 'nullable|integer|exists:product_faqs,id',
            'faqs.*.question' => 'required|string|max:1000',
            'faqs.*.answer' => 'nullable|string|max:4000',
            'faqs.*.sort_order' => 'nullable|integer|min:0',
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $faqs = $v->validated()['faqs'];

        DB::beginTransaction();
        try {
            $incomingIds = collect($faqs)->pluck('id')->filter()->values()->all();

            $product->faqs()->whereNotIn('id', $incomingIds)->delete();

            foreach ($faqs as $f) {
                if (!empty($f['id'])) {
                    $product->faqs()->where('id', $f['id'])->update([
                        'question' => $f['question'],
                        'answer' => $f['answer'] ?? null,
                        'sort_order' => $f['sort_order'] ?? 0,
                    ]);
                } else {
                    $product->faqs()->create([
                        'question' => $f['question'],
                        'answer' => $f['answer'] ?? null,
                        'sort_order' => $f['sort_order'] ?? 0,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'FAQs synced', 'faqs' => $product->faqs()->orderBy('sort_order')->get()], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('syncFaqs: '.$e->getMessage());
            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /admin/products/{product}/faqs/{faq}
    public function deleteFaq(Product $product, Faq $faq)
    {
        if ($faq->product_id !== $product->id) {
            return response()->json(['message' => 'FAQ does not belong to product'], Response::HTTP_FORBIDDEN);
        }

        $faq->delete();
        return response()->json(['message' => 'FAQ deleted'], 200);
    }
}
