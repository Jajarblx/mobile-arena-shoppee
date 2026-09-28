<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\RepairBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerCommerceTest extends TestCase
{
    use RefreshDatabase;

    private function addressData(array $overrides = []): array
    {
        return array_replace([
            'recipient_name' => 'Maya Cruz',
            'phone' => '09171234567',
            'region' => 'MIMAROPA',
            'province' => 'Oriental Mindoro',
            'city' => 'Calapan City',
            'barangay' => 'Lumang Bayan',
            'postal_code' => '5200',
            'street_address' => '12 Roxas Drive',
            'landmark' => 'Near the mall',
        ], $overrides);
    }

    private function checkoutData(string $token, array $overrides = []): array
    {
        return array_replace([
            'checkout_token' => $token,
            'customer_name' => 'Maya Cruz',
            'phone' => '09171234567',
            'email' => 'maya@example.test',
            'fulfillment' => 'pickup',
            'payment_method' => 'pay_at_store',
        ], $overrides);
    }

    public function test_customer_can_add_address_and_first_address_becomes_default(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('account.addresses.create'))->assertOk()->assertSee('Barangay');

        $this->actingAs($user)->post(route('account.addresses.store'), $this->addressData())
            ->assertRedirect(route('account.addresses'));

        $address = Address::sole();
        $this->assertSame($user->id, $address->user_id);
        $this->assertSame('+639171234567', $address->phone);
        $this->assertTrue($address->is_default);
        $this->get(route('account.addresses'))->assertOk()->assertSee('12 Roxas Drive');
    }

    public function test_customer_can_edit_own_address(): void
    {
        $user = User::factory()->create();
        $address = $user->addresses()->create($this->addressData());

        $this->actingAs($user)->get(route('account.addresses.edit', $address))->assertOk()->assertSee('Edit address');
        $this->actingAs($user)->patch(route('account.addresses.update', $address), $this->addressData(['street_address' => '44 New Street']))
            ->assertRedirect(route('account.addresses'));

        $this->assertSame('44 New Street', $address->fresh()->street_address);
    }

    public function test_customer_cannot_edit_or_delete_another_customers_address(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $address = $owner->addresses()->create($this->addressData());

        $this->actingAs($other)->get(route('account.addresses.edit', $address))->assertNotFound();
        $this->patch(route('account.addresses.update', $address), $this->addressData(['street_address' => 'Changed']))->assertNotFound();
        $this->patch(route('account.addresses.default', $address))->assertNotFound();
        $this->delete(route('account.addresses.destroy', $address))->assertNotFound();
        $this->assertSame('12 Roxas Drive', $address->fresh()->street_address);
    }

    public function test_customer_can_set_one_default_address(): void
    {
        $user = User::factory()->create();
        $first = $user->addresses()->create([...$this->addressData(), 'is_default' => true]);
        $second = $user->addresses()->create([...$this->addressData(['street_address' => 'Other Street']), 'is_default' => false]);

        $this->actingAs($user)->patch(route('account.addresses.default', $second))->assertRedirect();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
    }

    public function test_customer_can_delete_own_address_and_next_one_becomes_default(): void
    {
        $user = User::factory()->create();
        $first = $user->addresses()->create([...$this->addressData(), 'is_default' => true]);
        $second = $user->addresses()->create([...$this->addressData(['street_address' => 'Other Street']), 'is_default' => false]);

        $this->actingAs($user)->delete(route('account.addresses.destroy', $first))
            ->assertRedirect(route('account.addresses'));

        $this->assertDatabaseMissing('addresses', ['id' => $first->id]);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_guest_cannot_checkout_or_access_addresses(): void
    {
        $this->get(route('checkout.create'))->assertRedirect(route('login'));
        $this->post(route('checkout.store'), [])->assertRedirect(route('login'));
        $this->get(route('account.addresses'))->assertRedirect(route('login'));
    }

    public function test_checkout_links_user_uses_server_prices_and_is_idempotent(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $product = Product::firstOrFail();
        $token = (string) Str::uuid();
        $payload = $this->checkoutData($token, ['subtotal' => 1, 'user_id' => 999, 'status' => 'completed']);

        $this->actingAs($user)->withSession(['cart' => [$product->id => 2]])
            ->get(route('checkout.create'))->assertOk()->assertSee($product->name);

        $first = $this->actingAs($user)->withSession(['cart' => [$product->id => 2], 'checkout_token' => $token])
            ->post(route('checkout.store'), $payload);
        $first->assertRedirect();
        $order = Order::sole();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('processing', $order->status);
        $this->assertEquals((float) $product->price * 2, (float) $order->subtotal);
        $this->assertSame(2, $order->items()->firstOrFail()->quantity);
        $this->assertDatabaseCount('order_status_events', 1);

        $this->post(route('checkout.store'), $payload)->assertRedirect(route('account.orders.show', $order));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_checkout_rejects_stale_stock_without_creating_order(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $product = Product::firstOrFail();
        $token = (string) Str::uuid();
        $product->update(['stock' => 0]);

        $this->actingAs($user)->withSession(['cart' => [$product->id => 1], 'checkout_token' => $token])
            ->post(route('checkout.store'), $this->checkoutData($token))->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_detail_groups_multiple_items_under_one_order(): void
    {
        $this->seed();
        $user = User::factory()->create();
        [$first, $second] = Product::take(2)->get()->all();
        $token = (string) Str::uuid();

        $this->actingAs($user)->withSession(['cart' => [$first->id => 1, $second->id => 2], 'checkout_token' => $token])
            ->post(route('checkout.store'), $this->checkoutData($token))->assertRedirect();

        $order = Order::sole();
        $this->assertSame(2, $order->items()->count());
        $this->get(route('account.orders.show', $order))->assertOk()->assertSee($first->name)->assertSee($second->name);
    }

    public function test_checkout_can_use_saved_address_and_rejects_another_customers_address(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $address = $user->addresses()->create([...$this->addressData(), 'is_default' => true]);
        $otherAddress = $other->addresses()->create($this->addressData(['street_address' => 'Private Street']));
        $product = Product::firstOrFail();
        $token = (string) Str::uuid();
        $payload = $this->checkoutData($token, ['fulfillment' => 'delivery_request', 'address_choice' => 'saved', 'address_id' => $otherAddress->id, 'payment_method' => 'cash_on_delivery']);

        $this->actingAs($user)->withSession(['cart' => [$product->id => 1], 'checkout_token' => $token])
            ->post(route('checkout.store'), $payload)->assertSessionHasErrors('address_id');
        $this->assertDatabaseCount('orders', 0);

        $this->post(route('checkout.store'), [...$payload, 'address_id' => $address->id])->assertRedirect();
        $this->assertStringContainsString('12 Roxas Drive', Order::sole()->address);
        $this->assertEquals(99, (float) Order::sole()->shipping_fee);
    }

    public function test_checkout_can_add_an_address_and_keep_order_snapshot(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $product = Product::firstOrFail();
        $token = (string) Str::uuid();

        $this->actingAs($user)->withSession(['cart' => [$product->id => 1], 'checkout_token' => $token])
            ->post(route('checkout.store'), $this->checkoutData($token, [
                'fulfillment' => 'delivery_request',
                'payment_method' => 'cash_on_delivery',
                'address_choice' => 'new',
                'new_address' => $this->addressData(),
                'save_as_default' => '1',
            ]))->assertRedirect();

        $order = Order::sole();
        $this->assertDatabaseHas('addresses', ['user_id' => $user->id, 'street_address' => '12 Roxas Drive', 'is_default' => true]);
        $this->assertStringContainsString('12 Roxas Drive', $order->address);
        Address::sole()->update(['street_address' => 'Changed Later']);
        $this->assertStringContainsString('12 Roxas Drive', $order->fresh()->address);
    }

    public function test_customer_only_sees_own_orders_and_status_filters_work(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $own = $user->orders()->create($this->orderData('OWN-1', 'pending_payment'));
        $user->orders()->create($this->orderData('SHIP-1', 'to_ship'));
        $user->orders()->create($this->orderData('RECEIVE-1', 'shipped'));
        $user->orders()->create($this->orderData('DONE-1', 'completed'));
        $user->orders()->create($this->orderData('CANCEL-1', 'cancelled'));
        $otherOrder = $other->orders()->create($this->orderData('OTHER-1', 'shipped'));
        Order::create($this->orderData('LEGACY-1', 'pending'));

        $this->actingAs($user)->get(route('account.orders.show', $own))->assertOk()->assertSee('OWN-1');
        $this->get(route('account.orders.show', $otherOrder))->assertNotFound();
        $this->get(route('account.purchases'))->assertOk()->assertSee('OWN-1')->assertDontSee('OTHER-1')->assertDontSee('LEGACY-1');
        $this->get(route('account.purchases', ['status' => 'to_pay']))->assertOk()->assertSee('OWN-1');
        $this->get(route('account.purchases', ['status' => 'to_ship']))->assertOk()->assertSee('SHIP-1')->assertDontSee('OWN-1');
        $this->get(route('account.purchases', ['status' => 'to_receive']))->assertOk()->assertSee('RECEIVE-1')->assertDontSee('OWN-1');
        $this->get(route('account.purchases', ['status' => 'completed']))->assertOk()->assertSee('DONE-1')->assertDontSee('SHIP-1');
        $this->get(route('account.purchases', ['status' => 'cancelled']))->assertOk()->assertSee('CANCEL-1')->assertDontSee('DONE-1');
    }

    private function orderData(string $reference, string $status): array
    {
        return [
            'reference' => $reference,
            'customer_name' => 'Maya Cruz',
            'phone' => '09171234567',
            'fulfillment' => 'pickup',
            'payment_method' => 'pay_at_store',
            'subtotal' => 100,
            'status' => $status,
        ];
    }

    public function test_repair_booking_is_owned_and_private(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->post(route('repair.store'), [
            'customer_name' => 'Maya Cruz',
            'phone' => '09171234567',
            'device_type' => 'phone',
            'brand_model' => 'Private Phone',
            'service_type' => 'repair',
            'issue' => 'Screen does not respond.',
            'user_id' => $other->id,
        ])->assertOk();

        $repair = RepairBooking::sole();
        $this->assertSame($owner->id, $repair->user_id);
        $this->actingAs($other)->get(route('account.repairs'))->assertOk()->assertDontSee('Private Phone');
        $this->actingAs($owner)->get(route('account.repairs'))->assertOk()->assertSee('Private Phone')->assertSee('Screen does not respond.');
    }

    public function test_admin_can_view_and_update_statuses_but_customer_cannot(): void
    {
        $this->seed();
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $customer->orders()->create($this->orderData('ORDER-1', 'pending_payment'));
        $repair = $customer->repairBookings()->create([
            'reference' => 'REP-1', 'customer_name' => 'Maya Cruz', 'phone' => '09171234567',
            'device_type' => 'phone', 'brand_model' => 'Demo Phone', 'issue' => 'Broken screen',
            'service_type' => 'repair', 'status' => 'pending',
        ]);

        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        $this->patch(route('admin.orders.update', $order), ['status' => 'shipped'])->assertForbidden();
        $this->patch(route('admin.repairs.update', $repair), ['status' => 'diagnosing'])->assertForbidden();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('ORDER-1')->assertSee('REP-1');
        $this->patch(route('admin.orders.update', $order), ['status' => 'processing'])->assertRedirect();
        $this->patch(route('admin.repairs.update', $repair), ['status' => 'diagnosing', 'estimated_cost' => '1200.00', 'technician_notes' => 'Awaiting parts'])->assertRedirect();
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertDatabaseHas('order_status_events', ['order_id' => $order->id, 'status' => 'processing']);
        $this->assertSame('diagnosing', $repair->fresh()->status);
        $this->actingAs($customer)->get(route('account.repairs'))->assertOk()->assertSee('Awaiting parts')->assertSee('1,200.00');
        $this->get(route('account.orders.show', $order))->assertOk()->assertSee('Processing');
    }
}
