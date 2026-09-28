<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Services\AddressBook;
use App\Services\DeliveryPricing;
use App\Services\InventoryLedger;
use App\Services\CustomerNotifier;
use App\Support\AddressData;
use App\Support\MobileNumber;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function create(Request $request, CartController $cartController)
    {
        [$items, $subtotal] = $cartController->cartDetails();
        if ($items->isEmpty()) {
            return redirect()->route('products.index')->with('success', 'Add an item before checkout.');
        }
        if ($items->contains(fn ($item) => $item['stock_issue'])) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'One or more cart quantities exceed available stock. Please update your cart.']);
        }

        $token = $request->session()->get('checkout_token');
        if (! $token) {
            $token = (string) Str::uuid();
            $request->session()->put('checkout_token', $token);
        }

        $addresses = $request->user()->addresses()->orderByDesc('is_default')->latest()->get();

        return view('checkout.create', compact('items', 'subtotal', 'addresses', 'token'));
    }

    public function store(Request $request, AddressBook $addressBook, DeliveryPricing $deliveryPricing, InventoryLedger $inventory, CustomerNotifier $notifier)
    {
        $token = $request->validate(['checkout_token' => ['required', 'uuid']])['checkout_token'];
        $user = $request->user();
        $existing = $user->orders()->where('checkout_token', $token)->first();
        if ($existing) {
            return redirect()->route('account.orders.show', $existing);
        }

        if (is_array($request->input('new_address')) && is_string($request->input('new_address.phone'))) {
            $request->merge(['new_address' => [
                ...$request->input('new_address'),
                'phone' => MobileNumber::normalize((string) $request->input('new_address.phone')),
            ]]);
        }

        $newAddressRequired = $request->input('fulfillment') === 'delivery_request'
            && $request->input('address_choice') === 'new';
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'fulfillment' => ['required', 'in:pickup,delivery_request'],
            'address_choice' => ['required_if:fulfillment,delivery_request', 'nullable', Rule::in(['saved', 'new'])],
            'address_id' => [Rule::requiredIf($request->input('fulfillment') === 'delivery_request' && $request->input('address_choice') === 'saved'), 'nullable', 'integer', Rule::exists('addresses', 'id')->where('user_id', $request->user()->id)],
            ...AddressData::rules('new_address.', $newAddressRequired),
            'save_as_default' => ['nullable', 'boolean'],
            'payment_method' => ['required', 'in:pay_at_store,cash_on_pickup,cash_on_delivery,gcash_manual'],
            'notes' => ['nullable', 'string', 'max:800'],
        ]);
        if ($validated['fulfillment'] === 'delivery_request' && $validated['payment_method'] === 'cash_on_pickup') {
            throw ValidationException::withMessages(['payment_method' => 'Cash on pickup is only available for store collection.']);
        }
        if ($validated['fulfillment'] === 'pickup' && $validated['payment_method'] === 'cash_on_delivery') {
            throw ValidationException::withMessages(['payment_method' => 'Cash on delivery requires a delivery address.']);
        }
        if ($validated['fulfillment'] === 'delivery_request' && $validated['payment_method'] === 'pay_at_store') {
            throw ValidationException::withMessages(['payment_method' => 'Pay at store is available for pickup only. Choose cash on delivery or GCash.']);
        }

        if ($request->session()->get('checkout_token') !== $token) {
            throw ValidationException::withMessages(['checkout_token' => 'This checkout has expired. Please review your cart again.']);
        }

        try {
            $order = DB::transaction(function () use ($validated, $user, $addressBook, $deliveryPricing, $inventory, $notifier, $request, $token) {
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $alreadyPlaced = $user->orders()->where('checkout_token', $token)->first();
                if ($alreadyPlaced) {
                    return $alreadyPlaced;
                }
                $cart = $request->session()->get('cart', []);
                if (! is_array($cart) || $cart === []) {
                    throw ValidationException::withMessages(['cart' => 'Your cart is empty. Add a product before checkout.']);
                }

                $products = Product::whereIn('id', array_keys($cart))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $items = [];
                $subtotalCents = 0;
                foreach ($cart as $productId => $quantity) {
                    $product = $products->get((int) $productId);
                    if (! $product || ! $product->active || filter_var($quantity, FILTER_VALIDATE_INT) === false || (int) $quantity < 1 || (int) $quantity > $product->available_stock) {
                        throw ValidationException::withMessages(['cart' => 'An item in your cart is no longer available in that quantity. Please review your cart.']);
                    }

                    $items[] = ['product' => $product, 'quantity' => (int) $quantity];
                    $subtotalCents += (int) round((float) $product->price * 100) * (int) $quantity;
                }

                foreach (collect($items)->sortBy(fn ($item) => $item['product']->id) as $item) {
                    $quantity = $item['quantity'];
                    $allocated = Product::whereKey($item['product']->id)->where('active', true)
                        ->whereRaw('stock - reserved_stock >= ?', [$quantity])
                        ->update(['reserved_stock' => DB::raw('reserved_stock + '.(int) $quantity)]);
                    if ($allocated !== 1) {
                        throw ValidationException::withMessages(['cart' => 'An item was just reserved by another customer. Please review your cart.']);
                    }
                }

                $addressSnapshot = null;
                $shippingFee = 0;
                if ($validated['fulfillment'] === 'delivery_request') {
                    if ($validated['address_choice'] === 'saved') {
                        $address = $user->addresses()->findOrFail($validated['address_id']);
                    } else {
                        $address = $addressBook->create($user, $validated['new_address'], $request->boolean('save_as_default'));
                    }
                    $addressSnapshot = $address->delivery_snapshot;
                    $shippingFee = $deliveryPricing->forAddress($address)['fee'];
                }

                $awaitingPayment = $validated['payment_method'] === 'gcash_manual';

                $order = $user->orders()->create([
                    'reference' => 'MA-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                    'checkout_token' => $token,
                    'customer_name' => $validated['customer_name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? $user->email,
                    'fulfillment' => $validated['fulfillment'],
                    'address' => $addressSnapshot,
                    'payment_method' => $validated['payment_method'],
                    'notes' => $validated['notes'] ?? null,
                    'subtotal' => $subtotalCents / 100,
                    'shipping_fee' => $shippingFee,
                    'payment_status' => 'unpaid',
                    'stock_state' => 'reserved',
                    'reservation_expires_at' => $awaitingPayment ? now()->addMinutes(max(1, (int) config('mobile-arena.inventory.reservation_minutes'))) : null,
                    'status' => $awaitingPayment ? 'pending_payment' : 'processing',
                ]);

                foreach ($items as $item) {
                    $product = $item['product'];
                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit_price' => $product->price,
                        'quantity' => $item['quantity'],
                        'condition' => $product->condition_label,
                    ]);
                }
                $order->statusEvents()->create(['status' => $order->status, 'note' => 'Order placed']);
                foreach ($items as $item) {
                    $inventory->record($item['product'], $item['product']->fresh(), 'reservation', $user, $order, 'Order placed');
                }
                $notifier->send($user, 'order.placed', 'Order placed',
                    $order->reference.' has been placed successfully.', route('account.orders.show', $order));
                $notifier->admins('admin.order_placed', 'New customer order',
                    $order->reference.' is ready for review.', route('admin.orders.show', $order));

                return $order;
            }, 3);
        } catch (QueryException $exception) {
            $existing = $user->orders()->where('checkout_token', $token)->first();
            if (! $existing) {
                throw $exception;
            }
            $order = $existing;
        }

        $request->session()->forget(['cart', 'checkout_token']);

        return redirect()->route('account.orders.show', $order)->with('success', 'Your order has been placed.');
    }
}
