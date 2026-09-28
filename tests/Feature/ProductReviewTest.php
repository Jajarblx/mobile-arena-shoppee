<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function purchasedItem(User $customer, Product $product, string $status = 'completed'): OrderItem
    {
        $order = $customer->orders()->create([
            'reference' => 'TEST-'.Str::upper(Str::random(12)),
            'customer_name' => $customer->name,
            'phone' => '09171234567',
            'fulfillment' => 'pickup',
            'payment_method' => 'pay_at_store',
            'subtotal' => $product->price,
            'status' => $status,
        ]);

        return $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 1,
            'condition' => $product->condition,
        ]);
    }

    private function review(User $customer, Product $product, OrderItem $item, int $rating = 5): Review
    {
        $this->actingAs($customer)->post(route('reviews.store', $product), [
            'order_item_id' => $item->id, 'rating' => $rating,
            'title' => 'Carefully checked', 'body' => 'The product matched the listing and arrived in good condition.',
            'is_verified_purchase' => false, 'status' => 'hidden', 'user_id' => 999,
        ])->assertRedirect();

        return Review::where('order_item_id', $item->id)->firstOrFail();
    }

    public function test_completed_purchaser_can_review_and_verification_cannot_be_forged(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $item = $this->purchasedItem($customer, $product);
        $review = $this->review($customer, $product, $item);

        $this->assertSame($customer->id, $review->user_id);
        $this->assertTrue($review->is_verified_purchase);
        $this->assertSame('published', $review->status);
        $this->get(route('products.show', $product))->assertOk()->assertSee('Verified purchase')->assertSee('5.0');
        $this->get(route('account.purchases', ['status' => 'completed']))->assertOk()->assertSee('Edit review');
    }

    public function test_non_purchaser_and_incomplete_order_cannot_review(): void
    {
        $product = Product::firstOrFail();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $item = $this->purchasedItem($owner, $product);
        $data = ['order_item_id' => $item->id, 'rating' => 4, 'body' => 'Detailed customer review body.'];

        $this->post(route('reviews.store', $product), $data)->assertRedirect(route('login'));
        $this->actingAs($stranger)->post(route('reviews.store', $product), $data)->assertSessionHasErrors('order_item_id');
        $otherProduct = Product::whereKeyNot($product->id)->firstOrFail();
        $this->actingAs($owner)->post(route('reviews.store', $otherProduct), $data)->assertSessionHasErrors('order_item_id');
        $pending = $this->purchasedItem($owner, $product, 'processing');
        $this->actingAs($owner)->post(route('reviews.store', $product), [...$data, 'order_item_id' => $pending->id])
            ->assertSessionHasErrors('order_item_id');
        $this->assertDatabaseCount('reviews', 0);
        $this->get(route('account.purchases', ['status' => 'to_ship']))->assertOk()->assertDontSee('Rate product');
    }

    public function test_rating_range_and_duplicate_item_are_rejected(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $item = $this->purchasedItem($customer, $product);
        foreach ([0, 6] as $rating) {
            $this->actingAs($customer)->post(route('reviews.store', $product), [
                'order_item_id' => $item->id, 'rating' => $rating, 'body' => 'Detailed customer review body.',
            ])->assertSessionHasErrors('rating');
        }
        $this->review($customer, $product, $item);
        $this->post(route('reviews.store', $product), [
            'order_item_id' => $item->id, 'rating' => 3, 'body' => 'Another detailed customer review.',
        ])->assertSessionHasErrors('order_item_id');
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_only_owner_can_edit_or_delete_review(): void
    {
        $product = Product::firstOrFail();
        $owner = User::factory()->create();
        $review = $this->review($owner, $product, $this->purchasedItem($owner, $product));
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('reviews.edit', $review))->assertNotFound();
        $this->patch(route('reviews.update', $review), ['rating' => 1, 'body' => 'A changed review body.'])->assertNotFound();
        $this->delete(route('reviews.destroy', $review))->assertNotFound();
        $this->actingAs($owner)->get(route('reviews.edit', $review))->assertOk()->assertSee('Edit your review');
        $this->actingAs($owner)->patch(route('reviews.update', $review), [
            'rating' => 4, 'body' => 'Updated after another week of use.', 'is_verified_purchase' => false,
        ])->assertRedirect();
        $this->assertSame(4, $review->fresh()->rating);
        $this->assertTrue($review->fresh()->is_verified_purchase);
        $this->delete(route('reviews.destroy', $review))->assertRedirect();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_admin_can_hide_and_restore_and_hidden_review_does_not_affect_rating(): void
    {
        $product = Product::firstOrFail();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $low = $this->review($first, $product, $this->purchasedItem($first, $product), 1);
        $this->review($second, $product, $this->purchasedItem($second, $product), 5);

        $this->get(route('products.show', $product))->assertOk()->assertSee('3.0');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($first)->get(route('admin.reviews.index'))->assertForbidden();
        $this->patch(route('admin.reviews.moderate', $low), ['status' => 'hidden'])->assertForbidden();
        $this->actingAs($admin)->get(route('admin.reviews.index'))->assertOk();
        $this->patch(route('admin.reviews.moderate', $low), ['status' => 'hidden'])->assertRedirect();
        $this->assertSame('hidden', $low->fresh()->status);
        $this->get(route('products.show', $product))->assertOk()->assertSee('5.0')->assertSee('1 review');
        $this->patch(route('admin.reviews.moderate', $low), ['status' => 'published'])->assertRedirect();
        $this->get(route('products.show', $product))->assertOk()->assertSee('3.0');
    }

    public function test_unreviewed_product_has_honest_empty_state(): void
    {
        $product = Product::firstOrFail();
        $this->get(route('products.show', $product))->assertOk()->assertSee('No reviews yet');
    }
}
