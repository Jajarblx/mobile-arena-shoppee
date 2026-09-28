<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function slugs(array $query): array
    {
        return $this->get(route('products.index', $query))->assertOk()
            ->viewData('products')->getCollection()->pluck('slug')->all();
    }

    public function test_search_matches_name_brand_model_category_and_partial_case_insensitive_text(): void
    {
        $this->assertContains('iphone-13-128gb-brand-new', $this->slugs(['q' => 'IPHONE 13']));
        $this->assertContains('galaxy-a56-5g-brand-new', $this->slugs(['q' => 'sams']));
        $product = Product::where('slug', 'galaxy-a56-5g-brand-new')->firstOrFail();
        $product->update(['model_name' => 'Aurora 56']);
        $this->assertContains($product->slug, $this->slugs(['q' => 'aurora']));
        $this->assertContains('ipad-10th-gen-pre-owned', $this->slugs(['q' => 'TABLETS']));
        $this->assertContains('iphone-12-128gb-pre-owned', $this->slugs(['q' => 'second hand']));
        $this->assertSame([], $this->slugs(['q' => 'not-a-real-device-123']));
        $this->get('/shop?q=not-a-real-device-123')->assertOk()->assertSee('No matching products');
    }

    public function test_brand_category_condition_and_price_filters_combine(): void
    {
        $this->assertSame(['galaxy-s23-256gb-refurbished'], $this->slugs([
            'brand' => 'Samsung', 'category' => 'smartphones', 'condition' => 'refurbished',
            'min_price' => 20000, 'max_price' => 26000,
        ]));
        $this->assertContains('20w-usb-c-fast-charger', $this->slugs(['category' => 'accessories']));
        $this->assertNotContains('iphone-13-128gb-brand-new', $this->slugs(['brand' => 'Samsung']));
        $this->assertSame([], $this->slugs(['brand' => 'Samsung', 'max_price' => 1000]));
    }

    public function test_in_stock_uses_available_stock_and_invalid_filters_are_rejected(): void
    {
        $product = Product::where('slug', 'iphone-13-128gb-brand-new')->firstOrFail();
        $product->forceFill(['reserved_stock' => $product->stock])->save();
        $this->assertNotContains($product->slug, $this->slugs(['in_stock' => '1']));
        $this->assertContains($product->slug, $this->slugs([]));
        $this->get('/shop?min_price=100&max_price=10')->assertSessionHasErrors('max_price');
        $this->get('/shop?sort=untrusted')->assertSessionHasErrors('sort');
    }

    public function test_minimum_rating_uses_only_published_reviews_and_can_combine_with_filters(): void
    {
        $product = Product::where('slug', 'galaxy-s23-256gb-refurbished')->firstOrFail();
        $customer = User::factory()->create();
        $order = $customer->orders()->create([
            'reference' => 'CATALOG-REVIEW-1', 'customer_name' => $customer->name,
            'phone' => '09171234567', 'subtotal' => $product->price,
            'fulfillment' => 'pickup', 'payment_method' => 'pay_at_store', 'status' => 'completed',
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'unit_price' => $product->price, 'quantity' => 1, 'condition' => $product->condition,
        ]);
        $review = $item->review()->make(['rating' => 4, 'body' => 'A good refurbished product.']);
        $review->forceFill([
            'user_id' => $customer->id, 'product_id' => $product->id,
            'is_verified_purchase' => true, 'status' => 'published',
        ])->save();

        $this->assertSame([$product->slug], $this->slugs([
            'brand' => 'Samsung', 'condition' => 'refurbished', 'min_price' => 20000,
            'max_price' => 26000, 'min_rating' => '4',
        ]));
        $this->assertSame($product->slug, $this->slugs(['sort' => 'rating'])[0]);
        $this->assertSame([], $this->slugs(['min_rating' => '4', 'brand' => 'Apple']));
        $review->forceFill(['status' => 'hidden'])->save();
        $this->assertSame([], $this->slugs(['min_rating' => '4']));
    }

    public function test_price_newest_and_rating_sorts_have_correct_order(): void
    {
        $asc = $this->slugs(['sort' => 'price_asc']);
        $desc = $this->slugs(['sort' => 'price_desc']);
        $this->assertSame('20w-usb-c-fast-charger', $asc[0]);
        $this->assertSame('iphone-13-128gb-brand-new', $desc[0]);
        $new = Product::where('slug', '20w-usb-c-fast-charger')->firstOrFail()->replicate();
        $new->fill(['name' => 'Latest charger', 'slug' => 'latest-charger', 'sku' => 'MA-NEW-1']);
        $new->save();
        $this->assertSame('latest-charger', $this->slugs(['sort' => 'newest'])[0]);
        $this->assertCount(11, $this->slugs(['sort' => 'rating']));
    }

    public function test_inactive_product_is_hidden_but_historic_order_stays_intact(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $order = $customer->orders()->create([
            'reference' => 'HISTORIC-ORDER-1', 'customer_name' => $customer->name,
            'phone' => '09171234567', 'subtotal' => $product->price,
            'fulfillment' => 'pickup', 'payment_method' => 'pay_at_store', 'status' => 'completed',
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'unit_price' => $product->price, 'quantity' => 1, 'condition' => $product->condition,
        ]);
        $product->update(['active' => false]);
        $this->assertNotContains($product->slug, $this->slugs([]));
        $this->get(route('products.show', $product))->assertNotFound();
        $this->actingAs($customer)->get(route('account.orders.show', $order))->assertOk()->assertSee($product->name);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.products.edit', $product))->assertOk()->assertSee($product->name);
    }

    public function test_related_products_exclude_current_and_inactive_items_and_gallery_renders(): void
    {
        $product = Product::where('slug', 'iphone-13-128gb-brand-new')->firstOrFail();
        $product->update(['images' => ['products/phone-blue.svg', 'products/phone-pink.svg']]);
        $hidden = Product::where('slug', 'iphone-12-128gb-pre-owned')->firstOrFail();
        $hidden->update(['active' => false]);

        $response = $this->get(route('products.show', $product))->assertOk()
            ->assertSee('Product images')->assertSee('View image 2');
        $related = $response->viewData('related');
        $this->assertFalse($related->contains('id', $product->id));
        $this->assertFalse($related->contains('id', $hidden->id));
        $this->assertTrue($related->contains('category_id', $product->category_id));
    }

    public function test_inactive_saved_product_remains_manageable_in_account_wishlist(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $customer->wishlistProducts()->syncWithoutDetaching([$product->id]);
        $product->update(['active' => false]);

        $this->actingAs($customer)->get(route('account.wishlist'))->assertOk()
            ->assertSee($product->name)->assertSee('Currently unavailable')
            ->assertSee('Remove from wishlist');
        $this->delete(route('wishlist.destroy', $product))->assertRedirect();
        $this->assertSame(0, $customer->wishlistProducts()->count());
    }

    public function test_admin_can_create_and_edit_product_with_audited_opening_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.products.index'))->assertForbidden();
        $this->get(route('admin.products.create'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.products.create'))->assertOk();
        $category = Product::firstOrFail()->category;
        $data = [
            'category_id' => $category->id, 'name' => 'Arena Test Device', 'brand' => 'Mobile Arena',
            'model_name' => 'Arena One', 'condition' => 'refurbished', 'price' => '4999.00',
            'sku' => 'MA-ARENA-1', 'short_description' => 'A carefully inspected device.',
            'description' => 'An accurate prototype listing.', 'specifications_text' => "Battery: 88%\nInclusions: Cable",
            'image' => 'products/phone-blue.svg', 'images_text' => "products/phone-blue.svg\nproducts/phone-pink.svg",
            'active' => '1', 'featured' => '0', 'opening_stock' => 3,
            'stock_reason' => 'Initial shelf count',
        ];
        $this->post(route('admin.products.store'), $data)->assertRedirect();
        $product = Product::where('sku', 'MA-ARENA-1')->firstOrFail();
        $this->assertSame(3, $product->stock);
        $this->assertSame('Arena One', $product->model_name);
        $this->assertCount(2, $product->images);
        $this->assertSame(1, InventoryMovement::where('product_id', $product->id)->count());
        $this->assertSame(1, AuditLog::where('action', 'product.stock_adjusted')->where('auditable_id', $product->id)->count());
        $this->patch(route('admin.products.update', $product), [...$data, 'name' => 'Arena Test Device Updated',
            'stock' => 900, 'active' => '0'])->assertRedirect();
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertFalse($product->fresh()->active);
        $this->get(route('admin.products.index'))->assertOk()->assertSee('Arena Test Device Updated');
    }
}
