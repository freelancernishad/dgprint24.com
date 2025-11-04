<?php

namespace App\Http\Controllers\Global;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Shipping;
use App\Models\Turnaround;
use Illuminate\Http\Request;
use App\Helpers\HelpersFunctions;
use App\Models\PriceConfiguration;
use App\Http\Controllers\Controller;
use App\Models\TurnAroundTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Twilio\TwiML\Voice\Sip;

class CartController extends Controller
{
    /**
     * The session ID for the current request.
     *
     * @var string|null
     */
    protected $sessionId;

    public function __construct(Request $request)
    {
        $this->sessionId = $request->header('X-Session-ID') ?? $request->session()->getId();
    }

    /**
     * View all cart items (for user or guest)
     */
    public function index(Request $request)
    {
        if (Auth::check()) {
            $userId = Auth::id();

            // Merge guest cart if exists
            $this->mergeGuestCart($userId, $this->sessionId);

            $cartItems = Cart::with(['product', 'priceConfiguration', 'shipping', 'turnaround'])
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->get();
        } else {
            $cartItems = Cart::with(['product', 'priceConfiguration', 'shipping', 'turnaround'])
                ->where('session_id', $this->sessionId)
                ->where('status', 'pending')
                ->get();
        }

        // Format the response with price data from snapshots if relations are null
        $formattedItems = $cartItems->map(function ($item) {
            $itemArray = $item->toArray();

            // Use snapshot data if relations are null
            if (!$item->priceConfiguration && $item->price_snapshot) {
                $itemArray['price_configuration'] = $item->price_snapshot;
            }

            if (!$item->shipping && $item->shipping_snapshot) {
                $itemArray['shipping'] = $item->shipping_snapshot;
            }

            if (!$item->turnaround && $item->turnaround_snapshot) {
                $itemArray['turnaround'] = $item->turnaround_snapshot;
            }

            return $itemArray;
        });

        return response()->json($formattedItems);
    }

    /**
     * Add item to cart (guest or user)
     */
public function store(Request $request)
{
    // ১. ভ্যালিডেশন - নিরাপত্তা এবং নির্ভুলতার জন্য সব প্রয়োজনীয় ফিল্ড
    $validator = Validator::make($request->all(), [
        'product_id' => 'required|exists:products,product_id',
        'quantity' => 'required|integer|min:1',
        'total_sq_ft' => 'nullable|integer|min:1',
        'options' => 'nullable|array',
        'shipping_id' => 'nullable|string', // কোন শিপিং নির্বাচিত হয়েছে
        'turnaround_id' => 'nullable|string', // কোন টার্নআরাউন্ড নির্বাচিত হয়েছে
        'job_sample_price' => 'nullable|numeric|min:0',
        'digital_proof_price' => 'nullable|numeric|min:0',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // ২. প্রোডাক্ট খুঁজে বের করা
    $product = Product::where('product_id', $request->product_id)->where('active', true)->firstOrFail();

    // ৩. ব্যাকএন্ডে মূল্য যাচাই করা
    $pricingData = $this->getPricingData($request->product_id, $request->quantity, $request->options, $request->total_sq_ft);
    $data = $pricingData; // Updated to access the 'data' key from the new JSON structure


    $configuration_id = $data['breakdown']['configuration_id'] ?? null;



    // নির্বাচিত প্রাইস কনফিগারেশন খুঁজে বের করা
    $priceConfig = collect($data['price_config_list'])
        ->firstWhere('id', $configuration_id);




    if (!$priceConfig) {
        return response()->json(['error' => 'Invalid price configuration selected.'], 422);
    }

    // --- মূল্যের উপাদানগুলো বের করা ---

    // বেস মূল্য (কনফিগারেশন + পরিমাণ + বর্গফুট)
    $basePrice = (float) $data['breakdown']['final_price']; // Updated to use 'final_price' from the new structure

    // শিপিং মূল্য বের করা
    $shippingPrice = 0;
    $shippingDetails = null;

    if (!empty($request->shipping_id)) {
        // First check if shipping is available in the selected price configuration
        $shipping = collect($priceConfig['shippings'] ?? [])
            ->firstWhere('id', $request->shipping_id);

        if (!$shipping) {
            // If not found in price config, check global shipping options
            $shipping = collect($data['shippings'] ?? [])
                ->firstWhere('id', $request->shipping_id);
        }

        if ($shipping) {
            $shippingPrice = (float) $shipping['price'];
            $shippingDetails = $shipping; // সম্পূর্ণ ডেটা স্ন্যাপশট হিসেবে রাখা হলো
        }
    }

    // টার্নআরাউন্ড মূল্য বের করা
    $turnaroundPrice = 0;
    $turnaroundDetails = null;
    if (!empty($request->turnaround_id)) {
        // First check if turnaround is available in the selected price configuration
        $turnaround = collect($priceConfig['turnarounds'] ?? [])
            ->firstWhere('id', $request->turnaround_id);

        if (!$turnaround) {
            // If not found in price config, check global turnaround options
            $turnaround = collect($data['turnarounds'] ?? [])
                ->firstWhere('id', $request->turnaround_id);
        }

        if ($turnaround) {
            $turnaroundPrice = (float) $turnaround['price'];
            $turnaroundDetails = $turnaround; // সম্পূর্ণ ডেটা স্ন্যাপশট হিসেবে রাখা হলো
        }
    }

    // অতিরিক্ত মূল্য
     $jobSamplePrice = 0;
     $digitalProofPrice = 0;
    if($request->job_sample_price){
        $jobSamplePrice = (float) ($data['job_sample_price']);
    }

    if($request->digital_proof_price){
        $digitalProofPrice = (float) ($data['digital_proof_price']);
    }




    // ব্যাকএন্ডে সর্বমোট মূল্য ক্যালকুলেট করা
    $backendCalculatedPrice = $basePrice + $shippingPrice + $turnaroundPrice + $jobSamplePrice + $digitalProofPrice;



    // ফ্রন্টএন্ডের প্রাইস এবং ব্যাকএন্ডের প্রাইস মেলানো (নিরাপত্তা যাচাই)
    // if (abs($backendCalculatedPrice - $request->price) > 0.01) {
    //     return response()->json([
    //         'error' => 'Price validation failed. The price has been updated. Please refresh and try again.',
    //         'calculated_price' => $backendCalculatedPrice,
    //         'sent_price' => $request->price
    //     ], 422);
    // }


    $verifiedPrice = $backendCalculatedPrice;

    // --- বিস্তারিত মূল্য বিভাজন (price_breakdown) তৈরি করা ---
    $priceBreakdown = [
        'base_price' => [
            'label' => 'Product Price',
            'details' => $data['breakdown'], // বেস প্রাইসের সম্পূর্ণ ব্রেকডাউন
            'amount' => $basePrice,
        ],
        'shipping' => [
            'label' => 'Shipping',
            'details' => $shippingDetails, // নির্বাচিত শিপিংয়ের সম্পূর্ণ ডেটা
            'amount' => $shippingPrice,
        ],
        'turnaround' => [
            'label' => 'Turnaround Time',
            'details' => $turnaroundDetails, // নির্বাচিত টার্নআরাউন্ডের সম্পূর্ণ ডেটা
            'amount' => $turnaroundPrice,
        ],
        'extras' => [
            'job_sample_price' => $jobSamplePrice,
            'digital_proof_price' => $digitalProofPrice,
            'total_extras' => $jobSamplePrice + $digitalProofPrice,
        ],
        'total_price' => $verifiedPrice,
    ];

    // ৪. ইউজার বা সেশন আইডি নির্ধারণ
    if (Auth::check()) {
        $userId = Auth::id();
        $sessionId = null;
    } else {
        $userId = null;
        $sessionId = $this->sessionId;
    }

    // ৫. কার্টে একই পণ্য আছে কিনা চেক করা
    $cartItem = Cart::where(function ($query) use ($userId, $sessionId) {
            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('session_id', $sessionId);
            }
        })
        ->where('product_id', $request->product_id)
        ->where('options', json_encode($request->options ?? []))
        ->where('status', 'pending')
        ->first();

    if ($cartItem) {
        // ৬. যদি আইটেম আগে থেকেই থাকে, তবে পরিমাণ এবং মূল্য আপডেট করবে
        $cartItem->quantity += $request->quantity;
        $cartItem->price_at_time = $verifiedPrice; // যাচাইকৃত সর্বমোট মূল্য
        $cartItem->price_breakdown = $priceBreakdown; // বিস্তারিত ব্রেকডাউন আপডেট
        $cartItem->save();
    } else {
        // ৭. নতুন আইটেম হলে কার্টে যোগ করা
        $cartItem = Cart::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'product_id' => $product->id,
            'quantity' => $request->quantity,
            'price_at_time' => $verifiedPrice, // যাচাইকৃত সর্বমোট মূল্য
            'options' => $request->options ?? null,
            'price_breakdown' => $priceBreakdown, // বিস্তারিত ব্রেকডাউন সংরক্ষণ
            'status' => 'pending',
        ]);
    }

    return response()->json([
        'message' => 'Item added to cart successfully',
        'cart_item' => $cartItem
    ]);
}

    /**
     * Update cart item
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'options' => 'nullable|array',
            'price_configuration_id' => 'nullable|exists:price_configurations,id',
            'shipping_id' => 'nullable|exists:shippings,id',
            'turnaround_id' => 'nullable|exists:turnarounds,id',
            'expected_price' => 'nullable|numeric|min:0',
        ]);

        $cartItem = Cart::findOrFail($id);
        $this->authorizeCart($cartItem, $request);

        // Update quantity and options
        $cartItem->quantity = $request->quantity;
        if ($request->has('options')) {
            $cartItem->options = $request->options;
        }

        // If price configuration is updated, recalculate price
        if ($request->has('price_configuration_id')) {
            // Get pricing data
            $pricingData = $this->getPricingData($cartItem->product_id, $request->quantity, $cartItem->options ?? []);



            // Find the matching price configuration
            $priceConfig = collect($pricingData['price_config_list'])
                ->firstWhere('id', $request->price_configuration_id);

            if (!$priceConfig) {
                return response()->json(['error' => 'Invalid price configuration'], 422);
            }

            // Get shipping price if selected
            $shippingPrice = 0;
            $shippingSnapshot = null;
            if (!empty($request->shipping_id)) {
                $shipping = collect($priceConfig['shippings'])
                    ->firstWhere('id', $request->shipping_id);
                if ($shipping) {
                    $shippingPrice = (float) $shipping['price'];
                    $shippingSnapshot = $shipping;
                }
            }

            // Get turnaround price if selected
            $turnaroundPrice = 0;
            $turnaroundSnapshot = null;
            if (!empty($request->turnaround_id)) {
                $turnaround = collect($priceConfig['turnarounds'])
                    ->firstWhere('id', $request->turnaround_id);
                if ($turnaround) {
                    $turnaroundPrice = (float) $turnaround['price'];
                    $turnaroundSnapshot = $turnaround;
                }
            }

            // Calculate total price
            $calculatedPrice = (float) $priceConfig['price_after_discount']
                + $shippingPrice
                + $turnaroundPrice;

            // Validate price if provided
            if ($request->has('expected_price') && abs($calculatedPrice - $request->expected_price) > 0.01) {
                return response()->json([
                    'error' => 'Price validation failed. The price has been updated.',
                    'calculated_price' => $calculatedPrice,
                    'expected_price' => $request->expected_price
                ], 422);
            }

            // Update cart item with new pricing
            $cartItem->price_at_time = $calculatedPrice;
            $cartItem->price_configuration_id = $request->price_configuration_id;
            $cartItem->shipping_id = $request->shipping_id;
            $cartItem->turnaround_id = $request->turnaround_id;
            $cartItem->price_snapshot = $priceConfig;
            $cartItem->shipping_snapshot = $shippingSnapshot;
            $cartItem->turnaround_snapshot = $turnaroundSnapshot;

            // Update price breakdown
            $priceBreakdown = $cartItem->price_breakdown;
            $priceBreakdown['base_price'] = (float) $priceConfig['price_after_discount'];
            $priceBreakdown['original_price'] = (float) $priceConfig['original_price'];
            $priceBreakdown['discount_amount'] = (float) $priceConfig['discount_amount'];
            $priceBreakdown['discount_percentage'] = (float) $priceConfig['discount_percentage'];
            $priceBreakdown['shipping']['id'] = $request->shipping_id ?? null;
            $priceBreakdown['shipping']['price'] = $shippingPrice;
            $priceBreakdown['shipping']['label'] = $shippingSnapshot['shippingLabel'] ?? null;
            $priceBreakdown['turnaround']['id'] = $request->turnaround_id ?? null;
            $priceBreakdown['turnaround']['price'] = $turnaroundPrice;
            $priceBreakdown['turnaround']['label'] = $turnaroundSnapshot['turnaroundLabel'] ?? null;
            $priceBreakdown['total'] = $calculatedPrice;

            $cartItem->price_breakdown = $priceBreakdown;
        }

        $cartItem->save();

        return response()->json(['message' => 'Cart updated', 'cart' => $cartItem]);
    }

    /**
     * Remove item from cart
     */
    public function destroy(Request $request, $id)
    {
        $cartItem = Cart::findOrFail($id);
        $this->authorizeCart($cartItem, $request);

        $cartItem->delete();

        return response()->json(['message' => 'Item removed from cart']);
    }

    /**
     * Clear all items from cart
     */
    public function clear(Request $request)
    {
        if (Auth::check()) {
            Cart::where('user_id', Auth::id())->where('status', 'pending')->delete();
        } else {
            Cart::where('session_id', $this->sessionId)->where('status', 'pending')->delete();
        }

        return response()->json(['message' => 'Cart cleared']);
    }

    /**
     * Merge guest cart into user cart after login
     */
    private function mergeGuestCart($userId, $sessionId)
    {
        $guestItems = Cart::where('session_id', $sessionId)->where('status', 'pending')->get();

        foreach ($guestItems as $item) {
            $existingItem = Cart::where('user_id', $userId)
                ->where('product_id', $item->product_id)
                ->where('options', json_encode($item->options))
                ->where('status', 'pending')
                ->first();

            if ($existingItem) {
                // Update existing item with guest cart data
                $existingItem->quantity += $item->quantity;
                $existingItem->save();
                $item->delete();
            } else {
                // Transfer ownership to user
                $item->user_id = $userId;
                $item->session_id = null;
                $item->save();
            }
        }
    }

    /**
     * Ensure user or guest owns the cart item
     */
    private function authorizeCart(Cart $cartItem, Request $request)
    {
        if (Auth::check()) {
            if ($cartItem->user_id !== Auth::id()) {
                abort(403, 'Unauthorized');
            }
        } else {
            if ($cartItem->session_id !== $this->sessionId) {
                abort(403, 'Unauthorized');
            }
        }
    }

    /**
     * Get pricing data from API
     */
   /**
     * Get pricing data from the helper function.
     */
   /**
 * Get pricing data from the helper function.
 */
private function getPricingData($productId, $quantity, $options, $total_sq_ft = null)
{
    // প্যারামিটারগুলো একটি অ্যারেতে রূপান্তর করুন
    $params = [
        'runsize' => $quantity,
        'options' => $options,
        'sq_ft' => $total_sq_ft,
    ];

    // আপনার হেলপার ফাংশনকে কল করুন
    // এখন আমরা অ্যারে এবং productId পাস করছি
    $pricingData = HelpersFunctions::getPricingData($params, $productId);

    // --- ত্রুটি পরিচালনা (Error Handling) ---
    // হেলপার ফাংশনটি সঠিক ডাটা রিটার্ন করেছে কিনা তা পরীক্ষা করুন
    if (empty($pricingData) || !isset($pricingData['data'])) {
        // আপনি একটি এক্সেপশন থ্রো করতে পারেন বা একটি এরর মেসেজ দিয়ে স্টপ করতে পারেন
        abort(500, 'Failed to retrieve pricing data from the service.');
    }

    // কন্ট্রোলারের লজিক যেমনটা আশা করে, সেই 'data' অংশটি রিটার্ন করুন
    return $pricingData['data'];
}
}
