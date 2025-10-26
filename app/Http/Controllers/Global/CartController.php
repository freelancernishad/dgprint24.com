<?php

namespace App\Http\Controllers\Global;


use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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

            $cartItems = Cart::with('product')
                ->where('user_id', $userId)
                ->get();
        } else {

            $cartItems = Cart::with('product')
                ->where('session_id', $this->sessionId)
                ->get();
        }

        return response()->json($cartItems);
    }

    /**
     * Add item to cart (guest or user)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
            'options'    => 'nullable|array',
            'price_at_time' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = Product::findOrFail($request->product_id);

        if (Auth::check()) {
            $userId = Auth::id();
            $sessionId = null;
        } else {
            $userId = null;
            $sessionId = $this->sessionId;
        }

        // Check if the product already exists in cart
        $cartItem = Cart::where(function ($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })
            ->where('product_id', $request->product_id)
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $request->quantity;
            $cartItem->options = $request->options ?? $cartItem->options;
            $cartItem->save();
        } else {
            $cartItem = Cart::create([
                'user_id'       => $userId,
                'session_id'    => $sessionId,
                'product_id'    => $product->id,
                'quantity'      => $request->quantity,
                'price_at_time' => $request->price_at_time,
                'options'       => $request->options ?? null,
                'status'        => 'pending',
            ]);
        }

        return response()->json(['message' => 'Item added to cart', 'cart' => $cartItem]);
    }

    /**
     * Update cart item
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'options'  => 'nullable|array',
        ]);

        $cartItem = Cart::findOrFail($id);

        $this->authorizeCart($cartItem, $request);

        $cartItem->quantity = $request->quantity;
        if ($request->has('options')) {
            $cartItem->options = $request->options;
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
            Cart::where('user_id', Auth::id())->delete();
        } else {
            Cart::where('session_id', $this->sessionId)->delete();
        }

        return response()->json(['message' => 'Cart cleared']);
    }

    /**
     * Merge guest cart into user cart after login
     */
    private function mergeGuestCart($userId, $sessionId)
    {
        $guestItems = Cart::where('session_id', $sessionId)->get();

        foreach ($guestItems as $item) {
            $existingItem = Cart::where('user_id', $userId)
                ->where('product_id', $item->product_id)
                ->first();

            if ($existingItem) {
                $existingItem->quantity += $item->quantity;
                $existingItem->save();
                $item->delete();
            } else {
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
}
