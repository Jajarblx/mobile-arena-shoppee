<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\DeliveryPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function checkout(User $user, Product $product, int $quantity = 1, array $overrides = [])
    {
        $token = (string) Str::uuid();

        return $this->actingAs($user)
            ->withSession(['cart' => [$product->id => $quantity], 'checkout_token' => $token])
            ->post(route('checkout.store'), array_replace([
                'checkout_token' => $token,
                'customer_name' => $user->name,
                'phone' => '09171234567',
                'email' => $user->email,
                'fulfillment' => 'pickup',
                'payment_method' => 'pay_at_store',
            ], $overrides));
    }

    private function address(User $user, array $overrides = [])
    {
        return $user->addresses()->create(array_replace([
            'recipient_name' => $user->name,
            'phone' => '09171234567',
            'region' => 'MIMAROPA',
            'province' => 'Oriental Mindoro',
            'city' => 'Calapan City',
            'barangay' => 'Lumang Bayan',
            'postal_code' => '5200',
            'street_address' => '12 Roxas Drive',
            'is_default' => true,
        ], $overrides));
    }

    public function test_checkout_reserves_stock_atomically_and_rejects_a_second_customer(): void
    {
        $product = Product::firstOrFail();
        $product->update(['stock' => 2]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->checkout($first, $product, 2, ['subtotal' => 1, 'shipping_fee' => 0, 'reserved_stock' => 0])->assertRedirect();
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame(2, $product->fresh()->reserved_stock);
        $this->assertSame(0, $product->fresh()->available_stock);
        $this->assertSame('reserved', Order::sole()->stock_state);

        $this->checkout($second, $product)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 1);
        $this->get(route('products.show', $product))->assertOk()->assertSee('Out of Stock');
    }

    public function test_stale_cart_is_shown_and_checkout_requires_a_correction(): void
    {
        $product = Product::firstOrFail();
        $product->update(['stock' => 1]);
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['cart' => [$product->id => 2]])
            ->get(route('cart.index'))->assertOk()->assertSee('Only 1 available now');
        $this->get(route('checkout.create'))->assertRedirect(route('cart.index'));
        $this->patch(route('cart.update', $product), ['quantity' => 1])->assertRedirect();
        $this->get(route('checkout.create'))->assertOk();
    }

    public function test_customer_cancellation_releases_stock_once_and_other_customer_cannot_cancel(): void
    {
        $product = Product::firstOrFail();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->checkout($owner, $product)->assertRedirect();
        $order = Order::sole();

        $this->actingAs($other)->post(route('account.orders.cancel', $order))->assertNotFound();
        $this->actingAs($owner)->post(route('account.orders.cancel', $order))->assertRedirect();
        $this->post(route('account.orders.cancel', $order))->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('released', $order->fresh()->stock_state);
        $this->assertSame(0, $product->fresh()->reserved_stock);
        $this->assertDatabaseCount('order_status_events', 2);
    }

    public function test_fulfillment_converts_a_reservation_to_sold_stock_and_blocks_invalid_jumps(): void
    {
        $product = Product::firstOrFail();
        $product->update(['stock' => 2]);
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->checkout($customer, $product)->assertRedirect();
        $order = Order::sole();

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'shipped'])->assertSessionHasErrors('status');
        $this->patch(route('admin.orders.update', $order), ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->post(route('admin.orders.confirm-payment', $order))->assertRedirect();
        $this->patch(route('admin.orders.update', $order), ['status' => 'completed'])->assertRedirect();
        $this->assertSame('sold', $order->fresh()->stock_state);
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertSame(0, $product->fresh()->reserved_stock);
        $this->assertSame(1, $product->fresh()->available_stock);
        $this->patch(route('admin.orders.update', $order), ['status' => 'pending_payment'])->assertSessionHasErrors('status');
        $this->assertSame(1, $product->fresh()->stock);
        $this->actingAs($customer)->post(route('account.orders.cancel', $order))->assertSessionHasErrors('status');
    }

    public function test_delivery_transitions_require_shipping_and_payment_before_completion(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $address = $this->address($customer);
        $this->checkout($customer, $product, 1, [
            'fulfillment' => 'delivery_request', 'address_choice' => 'saved',
            'address_id' => $address->id, 'payment_method' => 'cash_on_delivery',
        ])->assertRedirect();
        $order = Order::sole();

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->patch(route('admin.orders.update', $order), ['status' => 'to_ship'])->assertRedirect();
        $this->patch(route('admin.orders.update', $order), ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->patch(route('admin.orders.update', $order), ['status' => 'shipped'])->assertRedirect();
        $this->patch(route('admin.orders.update', $order), ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->post(route('admin.orders.confirm-payment', $order))->assertRedirect();
        $this->patch(route('admin.orders.update', $order), ['status' => 'completed'])->assertRedirect();
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_customer_cannot_cancel_shipped_order(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $this->checkout($customer, $product)->assertRedirect();
        $order = Order::sole();
        $order->update(['status' => 'shipped']);

        $this->post(route('account.orders.cancel', $order))->assertSessionHasErrors('status');
        $this->assertSame('reserved', $order->fresh()->stock_state);
        $this->assertSame(1, $product->fresh()->reserved_stock);
        $this->get(route('account.orders.show', $order))->assertOk()->assertDontSee('Cancel order');
    }

    public function test_delivery_fee_is_calculated_from_saved_address_and_ignores_browser_total(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $address = $this->address($customer);
        $this->checkout($customer, $product, 1, [
            'fulfillment' => 'delivery_request', 'address_choice' => 'saved',
            'address_id' => $address->id, 'payment_method' => 'cash_on_delivery',
            'shipping_fee' => 0, 'subtotal' => 1, 'total' => 1, 'user_id' => 999,
        ])->assertRedirect();
        $order = Order::sole();
        $this->assertSame($customer->id, $order->user_id);
        $this->assertEquals(99, (float) $order->shipping_fee);
        $this->assertEquals((float) $product->price + 99, $order->total);
        $this->get(route('account.orders.show', $order))->assertOk()->assertSee('99.00');
    }

    public function test_delivery_pricing_covers_city_province_region_and_nationwide(): void
    {
        $user = User::factory()->create();
        $address = $this->address($user);
        $pricing = app(DeliveryPricing::class);
        $this->assertSame(['band' => 'local', 'fee' => 99], $pricing->forAddress($address));
        $address->city = 'Baco';
        $this->assertSame(['band' => 'province', 'fee' => 199], $pricing->forAddress($address));
        $address->province = 'Palawan';
        $this->assertSame(['band' => 'regional', 'fee' => 349], $pricing->forAddress($address));
        $address->region = 'NCR';
        $this->assertSame(['band' => 'national', 'fee' => 499], $pricing->forAddress($address));
    }

    public function test_manual_gcash_reference_requires_admin_verification_and_is_idempotent(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->checkout($customer, $product, 1, ['payment_method' => 'gcash_manual'])->assertRedirect();
        $order = Order::sole();
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertNotNull($order->reservation_expires_at);

        $this->actingAs($other)->post(route('account.orders.payment-reference', $order), ['payment_reference' => 'GCOTHER'])->assertNotFound();
        $this->actingAs($customer)->post(route('admin.orders.confirm-payment', $order))->assertForbidden();
        $this->post(route('account.orders.payment-reference', $order), ['payment_reference' => 'GC123456'])->assertRedirect();
        $this->assertSame('pending_verification', $order->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->reservation_expires_at);
        $this->post(route('account.orders.payment-reference', $order), ['payment_reference' => 'GCOTHER'])->assertSessionHasErrors('payment_reference');
        $this->actingAs($admin)->post(route('admin.orders.confirm-payment', $order))->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('processing', $order->fresh()->status);
        $count = $order->statusEvents()->count();
        $this->post(route('admin.orders.confirm-payment', $order))->assertRedirect();
        $this->assertSame($count, $order->statusEvents()->count());
    }

    public function test_admin_can_reject_and_customer_can_resubmit_manual_reference(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->checkout($customer, $product, 1, ['payment_method' => 'gcash_manual'])->assertRedirect();
        $order = Order::sole();

        $this->actingAs($admin)->post(route('admin.orders.confirm-payment', $order))->assertSessionHasErrors('payment_status');
        $this->actingAs($customer)->post(route('account.orders.payment-reference', $order), ['payment_reference' => 'GC1111'])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.orders.reject-payment', $order))->assertRedirect();
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->reservation_expires_at);
        $this->actingAs($customer)->post(route('account.orders.payment-reference', $order), ['payment_reference' => 'GC2222'])->assertRedirect();
        $this->assertSame('GC2222', $order->fresh()->payment_reference);
    }

    public function test_unverified_manual_reference_expires_and_cannot_be_confirmed_late(): void
    {
        config()->set('mobile-arena.inventory.verification_minutes', 1);
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->checkout($customer, $product, 1, ['payment_method' => 'gcash_manual'])->assertRedirect();
        $order = Order::sole();
        $this->actingAs($customer)->post(route('account.orders.payment-reference', $order), ['payment_reference' => 'GC4444'])->assertRedirect();

        $this->travel(2)->minutes();
        $this->actingAs($admin)->post(route('admin.orders.confirm-payment', $order))->assertSessionHasErrors('payment_status');
        $this->artisan('orders:expire-reservations')->assertSuccessful();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('released', $order->fresh()->stock_state);
        $this->assertSame(0, $product->fresh()->reserved_stock);
    }

    public function test_expired_unpaid_reservation_is_released_once_but_reference_under_review_is_kept(): void
    {
        $product = Product::firstOrFail();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->checkout($first, $product, 1, ['payment_method' => 'gcash_manual'])->assertRedirect();
        $firstOrder = Order::sole();
        $this->checkout($second, $product, 1, ['payment_method' => 'gcash_manual'])->assertRedirect();
        $secondOrder = Order::latest('id')->firstOrFail();
        $this->actingAs($second)->post(route('account.orders.payment-reference', $secondOrder), ['payment_reference' => 'GC3333'])->assertRedirect();

        $this->travel(31)->minutes();
        $this->artisan('orders:expire-reservations')->assertSuccessful();
        $this->artisan('orders:expire-reservations')->assertSuccessful();
        $this->assertSame('cancelled', $firstOrder->fresh()->status);
        $this->assertSame('pending_payment', $secondOrder->fresh()->status);
        $this->assertSame(1, $product->fresh()->reserved_stock);
        $this->assertDatabaseCount('order_status_events', 4);
    }

    public function test_admin_stock_adjustment_respects_reservations_and_customer_is_forbidden(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->checkout($customer, $product)->assertRedirect();

        $this->actingAs($customer)->patch(route('admin.products.stock', $product), ['stock' => 0])->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.products.stock', $product), ['stock' => 0, 'movement_type' => 'adjustment', 'reason' => 'Inventory count'])->assertSessionHasErrors('stock');
        $this->assertGreaterThanOrEqual(1, $product->fresh()->stock);
        $this->patch(route('admin.products.stock', $product), ['stock' => 1, 'movement_type' => 'adjustment', 'reason' => 'Inventory count'])->assertRedirect();
        $this->assertSame(0, $product->fresh()->available_stock);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Product inventory')->assertSee('Reserved');
    }
}
