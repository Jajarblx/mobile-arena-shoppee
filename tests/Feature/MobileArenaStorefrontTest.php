<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileArenaStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_homepage_loads_with_mobile_arena_branding(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Mobile Arena')
            ->assertSee('Brand New')
            ->assertSee('Pre-Owned')
            ->assertSee('Refurbished');
    }

    public function test_catalog_can_filter_by_condition(): void
    {
        $this->get('/shop?condition=refurbished')
            ->assertOk()
            ->assertSee('Galaxy S23 256GB')
            ->assertDontSee('iPhone 13 128GB');
    }

    public function test_product_can_be_added_to_cart(): void
    {
        $product = Product::firstOrFail();
        $this->post(route('cart.store', $product), ['quantity' => 2])
            ->assertRedirect()
            ->assertSessionHas('cart.'.$product->id, 2);
    }

    public function test_checkout_creates_order_and_clears_cart(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $token = (string) Str::uuid();
        $response = $this->actingAs($customer)->withSession(['cart' => [$product->id => 1], 'checkout_token' => $token])
            ->post('/checkout', [
                'checkout_token' => $token,
                'customer_name' => 'Prototype Customer',
                'phone' => '09170000000',
                'email' => 'customer@example.test',
                'fulfillment' => 'pickup',
                'payment_method' => 'pay_at_store',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseHas('orders', ['user_id' => $customer->id]);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Order details');
        $this->get(route('account.purchases'))->assertOk()->assertSee($product->name);
        $response->assertSessionMissing('cart');
    }

    public function test_repair_request_is_saved(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->post('/repair', [
            'customer_name' => 'Prototype Customer',
            'phone' => '09170000000',
            'device_type' => 'phone',
            'brand_model' => 'Sample Phone',
            'service_type' => 'repair',
            'issue' => 'Screen does not respond to touch.',
        ])->assertOk()->assertSee('Service request saved');

        $this->assertDatabaseCount('repair_bookings', 1);
        $this->assertDatabaseHas('repair_bookings', ['user_id' => $customer->id]);
        $this->get(route('account.repairs'))->assertOk()->assertSee('Sample Phone');
    }
}
