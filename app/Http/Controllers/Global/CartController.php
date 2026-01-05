<?php

namespace App\Http\Controllers\Global;

use App\Models\Cart;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\Product;
use App\Models\Shipping;
use App\Models\Turnaround;
use Twilio\TwiML\Voice\Sip;
use Illuminate\Http\Request;
use App\Models\TurnAroundTime;
use App\Helpers\HelpersFunctions;
use App\Models\PriceConfiguration;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use App\Helpers\ExternalTokenVerify;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Http\Resources\CartCollection;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Firebase\JWT\SignatureInvalidException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

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

        $this->sessionId =
            $request->header("X-Session-ID") ?? null;
    }

    /**
     * View all cart items (for user or guest)
     */
    public function getFormatedCartItems(Request $request)
    {
        $token = $request->bearerToken();
       $authUser =  ExternalTokenVerify::verifyExternalToken($token);

        if ($authUser) {
            $userId = $authUser->id ?? $this->sessionId;

            // Merge guest cart if exists
            $this->mergeGuestCart($userId, $this->sessionId);

            $cartItems = Cart::with([
                "product",
                "priceConfiguration",
                "shipping",
                "turnaround",
            ])
                ->where("session_id", $userId)
                ->where("status", "pending")
                ->get();
        } else {
            $cartItems = Cart::with([
                "product",
                "priceConfiguration",
                "shipping",
                "turnaround",
            ])
                ->where("session_id", $this->sessionId)
                ->where("status", "pending")
                ->get();
        }

        // Format the response with price data from snapshots if relations are null
        $formattedItems = $cartItems->map(function ($item) {
            $itemArray = $item->toArray();

            // Use snapshot data if relations are null
            if (!$item->priceConfiguration && $item->price_snapshot) {
                $itemArray["price_configuration"] = $item->price_snapshot;
            }

            if (!$item->shipping && $item->shipping_snapshot) {
                $itemArray["shipping"] = $item->shipping_snapshot;
            }

            if (!$item->turnaround && $item->turnaround_snapshot) {
                $itemArray["turnaround"] = $item->turnaround_snapshot;
            }


            return $itemArray;
        });


        return new CartCollection($cartItems);
        return response()->json($formattedItems);
    }  /**
     * View all cart items (for user or guest)
     */
    public function index(Request $request)
    {



        $token = $request->bearerToken();
       $authUser =  ExternalTokenVerify::verifyExternalToken($token);




        if ($authUser) {
            $userId = $authUser->id;

            // Merge guest cart if exists
            $this->mergeGuestCart($userId, $this->sessionId);

            $cartItems = Cart::with([
                "product",
                "priceConfiguration",
                "shipping",
                "turnaround",
            ])
                ->where("session_id", $userId)
                ->where("status", "pending")
                ->get();
        } else {
            $cartItems = Cart::with([
                "product",
                "priceConfiguration",
                "shipping",
                "turnaround",
            ])
                ->where("session_id", $this->sessionId)
                ->where("status", "pending")
                ->get();
        }

        // Format the response with price data from snapshots if relations are null
        $formattedItems = $cartItems->map(function ($item) {
            $itemArray = $item->toArray();

            // Use snapshot data if relations are null
            if (!$item->priceConfiguration && $item->price_snapshot) {
                $itemArray["price_configuration"] = $item->price_snapshot;
            }

            if (!$item->shipping && $item->shipping_snapshot) {
                $itemArray["shipping"] = $item->shipping_snapshot;
            }

            if (!$item->turnaround && $item->turnaround_snapshot) {
                $itemArray["turnaround"] = $item->turnaround_snapshot;
            }


            return $itemArray;
        });



        return response()->json(["data" => $formattedItems]);
    }

    /**
     * Add item to cart (guest or user)
     */
    public function store(Request $request)
    {
        // ১. ভ্যালিডেশন
        $validator = Validator::make($request->all(), [
            "product_id" => "required|exists:products,product_id",
            "quantity" => "required|integer|min:1",
            "total_sq_ft" => "nullable",
            "options" => "nullable|array",
            "shipping_id" => "nullable",
            "turnaround_id" => "nullable",
            "job_sample_price" => "nullable|numeric|min:0",
            "digital_proof_price" => "nullable|numeric|min:0",
            "delivery_address" => "nullable|array",
            "tax_id" => "nullable|exists:taxes,id",
            "sets" => "nullable|array",
            "sets.*" => "string",

            // নতুন: project_name
            "project_name" => "nullable|string|max:255",

            // নতুন: extra_selected_options (array of objects)
            "extra_selected_options" => "nullable|array",
            "extra_selected_options.*" => "nullable|array",
            "height" => "nullable",
            "width" => "nullable",
            "discount_or_add" => "nullable",
            "files" => "nullable|array",
            "files.*" => "nullable|string", // URL / path
        ]);

        if ($validator->fails()) {
            return response()->json(["errors" => $validator->errors()], 422);
        }

        // ২. প্রোডাক্ট খুঁজে বের করা
        $product = Product::where("product_id", $request->product_id)
            ->where("active", true)
            ->firstOrFail();

        // ৩. ব্যাকএন্ডে মূল্য যাচাই (getPricingData assumed)
        $pricingData = $this->getPricingData(
            $request->product_id,
            $request->quantity,
            $request->options,
            $request->total_sq_ft,
            $request->turnaround_id,
            $request->shipping_id
        );




        $data = $pricingData; // ধরে নিচ্ছি structure ঠিকই দেয়া হচ্ছে

        $configuration_id = $data["breakdown"]["configuration_id"] ?? null;

        $priceConfig = collect($data["price_config_list"] ?? [])->firstWhere(
            "id",
            $configuration_id
        );

        if (!$priceConfig && $product->type == "general") {
            return response()->json(
                ["error" => "Invalid price configuration selected."],
                422
            );
        }

        // --- মূল্যের উপাদানগুলো বের করা ---
        $basePrice = (float) ($data["breakdown"]["final_price"] ?? 0);

        $selected_turnaround = $data["breakdown"]["selected_turnaround"] ?? null;
        $selected_shipping = $data["breakdown"]["selected_shipping"] ?? null;

        $turnaroundPrice = 0;
        if (!empty($request->turnaround_id) && $selected_turnaround) {
            $turnaroundPrice = (float) ($selected_turnaround["price"] ?? 0);
        }

        $shippingPrice = 0;
        if (!empty($request->shipping_id) && $selected_shipping) {
            $shippingPrice = (float) ($selected_shipping["price"] ?? 0);
        }

        $jobSamplePrice = $request->filled('job_sample_price') ? (float) $request->job_sample_price : 0.0;
        $digitalProofPrice = $request->filled('digital_proof_price') ? (float) $request->digital_proof_price : 0.0;

        // auto-boolean flags
        $jobSampleFlag = $jobSamplePrice > 0 ? true : false;
        $digitalProofFlag = $digitalProofPrice > 0 ? true : false;



        $token = $request->bearerToken();
        $authUser = ExternalTokenVerify::verifyExternalToken($token);

        // ৫. ইউজার বা সেশন আইডি নির্ধারণ
        if ($authUser) {
            $userId = null;
            $sessionId = $authUser->id ?? $this->sessionId;
            Log::info("Authenticated user ID from token: " . $sessionId);
        } else {
            $userId = null;
            $sessionId = $this->sessionId;
        }

        // ================================
        // project_name handling (normalize)
        // ================================
        $projectNameRaw = $request->input('project_name', null);
        $projectName = null;
        if (is_string($projectNameRaw)) {
            $trimmed = trim($projectNameRaw);
            $projectName = $trimmed === '' ? null : $trimmed;
        }

        // ================================
        // extra_selected_options handling (normalize)
        // ================================
        $extrasRaw = $request->input('extra_selected_options', []);
        $normalizedExtras = [];

        if (is_array($extrasRaw)) {
            foreach ($extrasRaw as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $label = isset($item['label']) ? trim((string)$item['label']) : null;
                $selectedOption = isset($item['selected_option']) ? trim((string)$item['selected_option']) : null;
                $amount = isset($item['amount']) && $item['amount'] !== null && $item['amount'] !== '' ? (float)$item['amount'] : 0;
                $root = isset($item['__root']) ? trim((string)$item['__root']) : $label;
                $depth = isset($item['__depth']) ? (int)$item['__depth'] : 0;
                $randomIndex = isset($item['_randomIndex']) ? (int)$item['_randomIndex'] : null;

                $normalizedItem = [
                    'label' => $label,
                    'selected_option' => $selectedOption,
                    'amount' => $amount,
                    '__root' => $root,
                    '__depth' => $depth,
                ];

                if ($randomIndex !== null) {
                    $normalizedItem['_randomIndex'] = $randomIndex;
                }

                $normalizedExtras[] = $normalizedItem;
            }
        }

        // extra_selected_options এর amount sum করে quantity দিয়ে গুণ
        $extraOptionsTotal = 0.0; // per-unit মোট
        foreach ($normalizedExtras as $extra) {
            $extraOptionsTotal += (float) ($extra['amount'] ?? 0);
        }
        $extraOptionsWithQuantity = $extraOptionsTotal * (int) $request->quantity;

        // ================================
        // sets handling (store as JSON array, normalized)
        // ================================
        $setsRaw = $request->input('sets', []); // expect e.g. ["set one", "set two "]

        $normalizedSets = array_values(array_filter(array_map(function($s) {
            if (!is_string($s)) return null;
            return trim($s);
        }, $setsRaw)));

        // set_count
        $setCount = count($normalizedSets);



        // ================================
        // 8. files normalize (URL array)
        // ================================
        $normalizedFiles = array_values(array_unique(array_filter(array_map(function ($f) {
            return is_string($f) && trim($f) !== '' ? trim($f) : null;
        }, $request->input('files', [])))));

        // ================================
        // ব্যাকএন্ডে ট্যাক্স ব্যতিরেকে মোট
        // basePrice (ধরা হচ্ছে quantity সহ হিসাব করা)
        // + job_sample_price (একবার)
        // + digital_proof_price (একবার)
        // + (extra_selected_options_amount × quantity)
        // ================================
        $subtotalBeforeTax =
            $basePrice
            + $jobSamplePrice
            + $digitalProofPrice
            + $extraOptionsWithQuantity;

        // ৪. ট্যাক্স লোড করা ও ট্যাক্স মূল্য হিসাব করা (percent)
        $taxPrice = 0.0;
        $taxModel = null;
        if (!empty($request->tax_id)) {
            $taxModel = \App\Models\Tax::find($request->tax_id);
            if ($taxModel) {
                $taxPercentage = (float) $taxModel->price;


                // $taxPrice = round(($subtotalBeforeTax * $taxPercentage) / 100, 2);


                if ($setCount > 0) {
                    $final_price_without_turnaroundByCount = (float) ($data["breakdown"]["final_price_without_turnaround"] ?? 0) * $setCount;
                    $turnaround_priceByCount = (float) ($data["breakdown"]["turnaround_price"] ?? 0) * $setCount;
                    $shipping_priceByCount = (float) ($data["breakdown"]["shipping_price"] ?? 0);

                    $jobSamplePriceByCount =  $jobSamplePrice* $setCount;
                    $extraOptionsWithQuantityPrice = $extraOptionsWithQuantity * $setCount;

                    $subtotalBeforeTaxByCount = $final_price_without_turnaroundByCount + $turnaround_priceByCount + $shipping_priceByCount + $jobSamplePriceByCount + $extraOptionsWithQuantityPrice;



                    $taxPrice = round(($subtotalBeforeTaxByCount * $taxPercentage) / 100, 2);

                }
            }
        }

        // ফাইনাল ব্যাকএন্ড ক্যালকুলেটেড প্রাইস (tax সহ)
        $backendCalculatedPrice = $subtotalBeforeTax + $taxPrice;
        $verifiedPrice = $backendCalculatedPrice;

        // price_breakdown-এ ট্যাক্সের তথ্য + extras এর details যোগ করা
        $priceBreakdown = [
            "base_price" => [
                "label" => "Product Price",
                "details" => $data["breakdown"] ?? null,
                "amount" => $basePrice,
            ],
            "shipping" => [
                "label" => "Shipping",
                "details" => $selected_shipping,
                "amount" => $shippingPrice,
            ],
            "turnaround" => [
                "label" => "Turnaround Time",
                "details" => $selected_turnaround,
                "amount" => $turnaroundPrice,
            ],
            "extras" => [
                "job_sample_price" => $jobSamplePrice,
                "digital_proof_price" => $digitalProofPrice,
                "extra_selected_options_per_unit" => $extraOptionsTotal,
                "extra_selected_options_total" => $extraOptionsWithQuantity,
                "total_extras_without_quantity" => $jobSamplePrice + $digitalProofPrice + $extraOptionsTotal,
                "total_extras_with_quantity" => $jobSamplePrice + $digitalProofPrice + $extraOptionsWithQuantity,
            ],
            "subtotal_before_tax" => $subtotalBeforeTax,
            "tax" => [
                "tax_id" => $taxModel ? $taxModel->id : null,
                "tax_percentage" => $taxModel ? (float) $taxModel->price : 0,
                "tax_amount" => $taxPrice,
            ],
            "total_price" => $verifiedPrice,
        ];

        // ================================
        // find existing cart item (compare options + sets + project_name + extra_selected_options + job/digital flags)
        // ================================
        $cartQuery = Cart::where(function ($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where("user_id", $userId);
                } else {
                    $query->where("session_id", $sessionId);
                }
            })
            ->where("product_id", $product->id)
            ->where("status", "pending");

        // compare options
        if (!empty($request->options)) {
            $cartQuery->where('options', json_encode($request->options));
        } else {
            $cartQuery->where(function($q){
                $q->whereNull('options')->orWhere('options', json_encode([]));
            });
        }

        // compare sets (store & compare JSON exact match)
        $setsJson = json_encode($normalizedSets);
        $cartQuery->where('sets', $setsJson);

        // compare project_name (exact match or both null)
        if ($projectName !== null) {
            $cartQuery->where('project_name', $projectName);
        } else {
            $cartQuery->where(function($q) {
                $q->whereNull('project_name')->orWhere('project_name', '');
            });
        }

        // compare extra_selected_options (exact JSON match)
        if (!empty($normalizedExtras)) {
            $extrasJson = json_encode($normalizedExtras);
            $cartQuery->where('extra_selected_options', $extrasJson);
        } else {
            $cartQuery->where(function($q) {
                $q->whereNull('extra_selected_options')->orWhere('extra_selected_options', json_encode([]));
            });
        }

        // compare boolean flags too (important to avoid merging items with different flags)
        $cartQuery->where('job_sample', $jobSampleFlag);
        $cartQuery->where('digital_proof', $digitalProofFlag);

        $cartItem = $cartQuery->first();

        if ($cartItem) {
            // ৭. যদি আইটেম আগে থেকেই থাকে, পরিমাণ ও মূল্য আপডেট করা
            $cartItem->quantity += $request->quantity;
            $cartItem->price_at_time = $verifiedPrice;
            $cartItem->price_breakdown = $priceBreakdown;
            $cartItem->turnarounds = $selected_turnaround;
            $cartItem->shippings = $selected_shipping;
            $cartItem->delivery_address = $request->delivery_address ?? null;
            $cartItem->tax_id = $taxModel ? $taxModel->id : null;
            $cartItem->tax_price = $taxPrice;
            $cartItem->sets = $normalizedSets;   // saved as JSON
            $cartItem->set_count = $setCount;
            $cartItem->project_name = $projectName; // NEW
            $cartItem->extra_selected_options = $normalizedExtras; // NEW
            $cartItem->files = $normalizedFiles;
            $cartItem->height = $request->height ?? null;
            $cartItem->width = $request->width ?? null;
            $cartItem->discount_or_add = $request->discount_or_add ?? 0;

            // NEW: save job/digital prices & flags
            $cartItem->job_sample_price = $jobSamplePrice;
            $cartItem->digital_proof_price = $digitalProofPrice;

            $cartItem->job_sample = $jobSampleFlag;
            $cartItem->digital_proof = $digitalProofFlag;

            $cartItem->save();
        } else {
            // ৮. নতুন কার্ট আইটেম তৈরি
            $cartItem = Cart::create([
                "user_id" => $userId,
                "session_id" => $sessionId,
                "product_id" => $product->id,
                "quantity" => $request->quantity,
                "price_at_time" => $verifiedPrice,
                "options" => $request->options ?? null,
                "price_breakdown" => $priceBreakdown,
                "turnarounds" => $selected_turnaround,
                "shippings" => $selected_shipping,
                "delivery_address" => $request->delivery_address ?? null,
                "width" => $request->width ?? null,
                "height" => $request->height ?? null,
                "discount_or_add" => $request->discount_or_add ?? 0,
                "status" => "pending",
                "tax_id" => $taxModel ? $taxModel->id : null,
                "tax_price" => $taxPrice,
                "sets" => $normalizedSets, // JSON array
                "set_count" => $setCount,
                "project_name" => $projectName, // NEW
                "extra_selected_options" => $normalizedExtras, // NEW
                "files" => $normalizedFiles, // NEW

                // NEW: job/digital prices & flags
                "job_sample_price" => $jobSamplePrice,
                "digital_proof_price" => $digitalProofPrice,
                "job_sample" => $jobSampleFlag,
                "digital_proof" => $digitalProofFlag,
            ]);
        }

        // রিফ্রেশ করে নতুন ডেটা পাঠানো ভাল
        $cartItem->refresh();

        return response()->json([
            "message" => "Item added to cart successfully",
            "cart_item" => $cartItem,
        ]);
    }



protected function decodeJwtPayloadUnsafe(?string $token)
{
    if (!$token) return null;
    // token parts: header.payload.signature
    $parts = explode('.', $token);
    if (count($parts) < 2) return null;
    $payload = $parts[1];
    // base64url decode
    $payload = str_replace(['-', '_'], ['+', '/'], $payload);
    $padding = 4 - (strlen($payload) % 4);
    if ($padding !== 4) $payload .= str_repeat('=', $padding);
    $json = base64_decode($payload);
    return json_decode($json, true);
}


    /**
     * Update cart item
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            "quantity" => "required|integer|min:1",
            "options" => "nullable|array",
            "price_configuration_id" =>
                "nullable|exists:price_configurations,id",
            "shipping_id" => "nullable|exists:shippings,id",
            "turnaround_id" => "nullable|exists:turnarounds,id",
            "expected_price" => "nullable|numeric|min:0",
        ]);

        $cartItem = Cart::findOrFail($id);
        $this->authorizeCart($cartItem, $request);

        // Update quantity and options
        $cartItem->quantity = $request->quantity;
        if ($request->has("options")) {
            $cartItem->options = $request->options;
        }

        // If price configuration is updated, recalculate price
        if ($request->has("price_configuration_id")) {
            // Get pricing data
            $pricingData = $this->getPricingData(
                $cartItem->product_id,
                $request->quantity,
                $cartItem->options ?? []
            );

            // Find the matching price configuration
            $priceConfig = collect(
                $pricingData["price_config_list"]
            )->firstWhere("id", $request->price_configuration_id);

            if (!$priceConfig) {
                return response()->json(
                    ["error" => "Invalid price configuration"],
                    422
                );
            }

            // Get shipping price if selected
            $shippingPrice = 0;
            $shippingSnapshot = null;
            if (!empty($request->shipping_id)) {
                $shipping = collect($priceConfig["shippings"])->firstWhere(
                    "id",
                    $request->shipping_id
                );
                if ($shipping) {
                    $shippingPrice = (float) $shipping["price"];
                    $shippingSnapshot = $shipping;
                }
            }

            // Get turnaround price if selected
            $turnaroundPrice = 0;
            $turnaroundSnapshot = null;
            if (!empty($request->turnaround_id)) {
                $turnaround = collect($priceConfig["turnarounds"])->firstWhere(
                    "id",
                    $request->turnaround_id
                );
                if ($turnaround) {
                    $turnaroundPrice = (float) $turnaround["price"];
                    $turnaroundSnapshot = $turnaround;
                }
            }

            // Calculate total price
            $calculatedPrice =
                (float) $priceConfig["price_after_discount"] +
                $shippingPrice +
                $turnaroundPrice;

            // Validate price if provided
            if (
                $request->has("expected_price") &&
                abs($calculatedPrice - $request->expected_price) > 0.01
            ) {
                return response()->json(
                    [
                        "error" =>
                            "Price validation failed. The price has been updated.",
                        "calculated_price" => $calculatedPrice,
                        "expected_price" => $request->expected_price,
                    ],
                    422
                );
            }

            // Update cart item with new pricing
            $cartItem->price_at_time = $calculatedPrice;
            $cartItem->price_configuration_id =
                $request->price_configuration_id;
            $cartItem->shipping_id = $request->shipping_id;
            $cartItem->turnaround_id = $request->turnaround_id;
            $cartItem->price_snapshot = $priceConfig;
            $cartItem->shipping_snapshot = $shippingSnapshot;
            $cartItem->turnaround_snapshot = $turnaroundSnapshot;

            // Update price breakdown
            $priceBreakdown = $cartItem->price_breakdown;
            $priceBreakdown["base_price"] =
                (float) $priceConfig["price_after_discount"];
            $priceBreakdown["original_price"] =
                (float) $priceConfig["original_price"];
            $priceBreakdown["discount_amount"] =
                (float) $priceConfig["discount_amount"];
            $priceBreakdown["discount_percentage"] =
                (float) $priceConfig["discount_percentage"];
            $priceBreakdown["shipping"]["id"] = $request->shipping_id ?? null;
            $priceBreakdown["shipping"]["price"] = $shippingPrice;
            $priceBreakdown["shipping"]["label"] =
                $shippingSnapshot["shippingLabel"] ?? null;
            $priceBreakdown["turnaround"]["id"] =
                $request->turnaround_id ?? null;
            $priceBreakdown["turnaround"]["price"] = $turnaroundPrice;
            $priceBreakdown["turnaround"]["label"] =
                $turnaroundSnapshot["turnaroundLabel"] ?? null;
            $priceBreakdown["total"] = $calculatedPrice;

            $cartItem->price_breakdown = $priceBreakdown;
        }

        $cartItem->save();

        return response()->json([
            "message" => "Cart updated",
            "cart" => $cartItem,
        ]);
    }

    /**
     * Remove item from cart
     */
    public function destroy(Request $request, $id)
    {
        $cartItem = Cart::findOrFail($id);
        $this->authorizeCart($cartItem, $request);

        $cartItem->delete();

        return response()->json(["message" => "Item removed from cart"]);
    }

    /**
     * Clear all items from cart
     */
    public function clear(Request $request)
    {
        if (Auth::check()) {
            Cart::where("user_id", Auth::id())
                ->where("status", "pending")
                ->delete();
        } else {
            Cart::where("session_id", $this->sessionId)
                ->where("status", "pending")
                ->delete();
        }

        return response()->json(["message" => "Cart cleared"]);
    }

    /**
     * Merge guest cart into user cart after login
     */
    private function mergeGuestCart($userId, $sessionId)
    {
        $guestItems = Cart::where("session_id", $sessionId)
            ->where("status", "pending")
            ->get();

        foreach ($guestItems as $item) {
            $existingItem = Cart::where("session_id", $userId)
                ->where("product_id", $item->product_id)
                ->where("options", json_encode($item->options))
                ->where("status", "pending")
                ->first();

            if ($existingItem) {
                // Update existing item with guest cart data
                $existingItem->quantity += $item->quantity;
                $existingItem->save();
                $item->delete();
            } else {
                // Transfer ownership to user
                $item->session_id = $userId;
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


        $token = $request->bearerToken();
        $authUser = ExternalTokenVerify::verifyExternalToken($token);

        // ৫. ইউজার বা সেশন আইডি নির্ধারণ
        if ($authUser) {
            $sessionId = $authUser->id;
            if ($cartItem->session_id !== $sessionId) {
                abort(403, "Unauthorized");
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
    private function getPricingData(
        $productId,
        $quantity,
        $options,
        $total_sq_ft = null,
        $turnaround_id = null,
        $shipping_id = null
    ) {
        // প্যারামিটারগুলো একটি অ্যারেতে রূপান্তর করুন
        $params = [
            "runsize" => $quantity,
            "options" => $options,
            "sq_ft" => $total_sq_ft,
            "turn_around_times_id" => $turnaround_id ?? null,
            "shipping_id" => $shipping_id ?? null,
        ];

        Log::info(
            "Fetching pricing data for product ID: " .
                $productId .
                " with params: " .
                json_encode($params)
        );

        // আপনার হেলপার ফাংশনকে কল করুন
        // এখন আমরা অ্যারে এবং productId পাস করছি
        $pricingData = HelpersFunctions::getPricingData($params, $productId);

        // --- ত্রুটি পরিচালনা (Error Handling) ---
        // হেলপার ফাংশনটি সঠিক ডাটা রিটার্ন করেছে কিনা তা পরীক্ষা করুন
        if (empty($pricingData) || !isset($pricingData["data"])) {
            // আপনি একটি এক্সেপশন থ্রো করতে পারেন বা একটি এরর মেসেজ দিয়ে স্টপ করতে পারেন
            abort(500, "Failed to retrieve pricing data from the service.");
        }

        // কন্ট্রোলারের লজিক যেমনটা আশা করে, সেই 'data' অংশটি রিটার্ন করুন
        return $pricingData["data"];
    }

    function clearCartItems($id)
    {
        Cart::where("session_id", $id)
            ->where("status", "pending")
            ->delete();

        return response()->json(["message" => "Cart items cleared for session_id: $id"]);
    }



}
