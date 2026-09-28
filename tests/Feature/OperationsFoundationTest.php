<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RepairBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function place(User $customer, Product $product, string $payment = 'pay_at_store', int $quantity = 1, array $extra = []): Order
    {
        $token = (string) Str::uuid();
        $data = array_replace([
            'checkout_token' => $token,
            'customer_name' => $customer->name,
            'phone' => '09171234567',
            'email' => $customer->email,
            'fulfillment' => 'pickup',
            'payment_method' => $payment,
        ], $extra);
        $this->actingAs($customer)->withSession(['cart' => [$product->id => $quantity], 'checkout_token' => $token])
            ->post(route('checkout.store'), $data)->assertRedirect();

        return $customer->orders()->latest('id')->firstOrFail();
    }

    private function completePickup(User $customer, User $admin, Product $product): Order
    {
        $order = $this->place($customer, $product);
        $this->actingAs($admin)->post(route('admin.orders.confirm-payment', $order))->assertRedirect();
        $this->patch(route('admin.orders.update', $order), ['status' => 'completed'])->assertRedirect();

        return $order->fresh();
    }

    private function requestRefund(User $customer, Order $order): Refund
    {
        $this->actingAs($customer)->post(route('account.refunds.store', $order), [
            'reason' => 'The device does not match the listing condition.',
            'amount' => 999999,
            'user_id' => 999999,
        ])->assertRedirect();

        return Refund::where('order_id', $order->id)->firstOrFail();
    }

    public function test_stock_adjustment_requires_reason_and_creates_movement_and_audit(): void
    {
        $product = Product::firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);
        $before = $product->stock;

        $this->actingAs($admin)->patch(route('admin.products.stock', $product), [
            'stock' => $before + 2, 'movement_type' => 'correction',
        ])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('inventory_movements', 0);

        $this->patch(route('admin.products.stock', $product), [
            'stock' => $before + 1, 'movement_type' => 'damage', 'reason' => 'Damaged display unit',
        ])->assertSessionHasErrors('stock');
        $this->assertDatabaseCount('inventory_movements', 0);

        $this->patch(route('admin.products.stock', $product), [
            'stock' => $before + 2, 'movement_type' => 'correction', 'reason' => 'Physical cycle count',
        ])->assertRedirect();
        $movement = InventoryMovement::sole();
        $this->assertSame('correction', $movement->type);
        $this->assertSame(2, $movement->quantity_change);
        $this->assertSame($before, $movement->stock_before);
        $this->assertSame($before + 2, $movement->stock_after);
        $this->assertSame($admin->id, $movement->user_id);
        $this->assertSame('product.stock_adjusted', AuditLog::sole()->action);
        $this->get(route('admin.products.history', $product))->assertOk()->assertSee('Physical cycle count');
    }

    public function test_completed_sale_creates_stock_movement_and_staff_status_audit(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $before = $product->stock;
        $order = $this->completePickup($customer, $admin, $product);

        $sale = InventoryMovement::where('type', 'sale')->sole();
        $this->assertSame(-1, $sale->quantity_change);
        $this->assertSame($before - 1, $sale->stock_after);
        $this->assertSame('Order', $sale->reference_type);
        $this->assertSame($order->id, $sale->reference_id);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id, 'action' => 'payment.confirmed', 'auditable_id' => $order->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id, 'action' => 'order.status_changed', 'auditable_id' => $order->id,
        ]);
    }

    public function test_delivery_tracking_is_audited_and_visible_only_to_owner(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $address = $customer->addresses()->create([
            'recipient_name' => $customer->name, 'phone' => '09171234567', 'region' => 'MIMAROPA',
            'province' => 'Oriental Mindoro', 'city' => 'Calapan City', 'barangay' => 'Lumang Bayan',
            'postal_code' => '5200', 'street_address' => '12 Roxas Drive', 'is_default' => true,
        ]);
        $order = $this->place($customer, $product, 'cash_on_delivery', 1, [
            'fulfillment' => 'delivery_request', 'address_choice' => 'saved', 'address_id' => $address->id,
        ]);

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'shipped'])->assertSessionHasErrors('status');
        $this->patch(route('admin.orders.update', $order), ['status' => 'to_ship'])->assertRedirect();
        $this->patch(route('admin.orders.update', $order), [
            'status' => 'shipped', 'courier_name' => 'Local courier', 'tracking_number' => 'MA-TRACK-1',
        ])->assertRedirect();
        $this->assertNotNull($order->fresh()->shipped_at);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('MA-TRACK-1');
        $this->actingAs($customer)->get(route('account.orders.show', $order))->assertOk()->assertSee('MA-TRACK-1');
        $this->actingAs($other)->get(route('account.orders.show', $order))->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.status_changed', 'auditable_id' => $order->id]);

        $this->actingAs($admin)->patch(route('admin.orders.tracking', $order), [
            'courier_name' => 'Local courier', 'tracking_number' => 'MA-TRACK-2',
        ])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.tracking_updated', 'auditable_id' => $order->id]);
        $this->actingAs($customer)->patch(route('admin.orders.tracking', $order), [
            'tracking_number' => 'TAMPER',
        ])->assertForbidden();
    }

    public function test_pickup_cannot_receive_shipping_data(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->place($customer, Product::firstOrFail());
        $this->actingAs($admin)->post(route('admin.orders.confirm-payment', $order))->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), [
            'status' => 'completed', 'courier_name' => 'Courier',
        ])->assertSessionHasErrors('tracking_number');
        $this->patch(route('admin.orders.tracking', $order), [
            'courier_name' => 'Courier',
        ])->assertSessionHasErrors('tracking_number');
        $this->assertNull($order->fresh()->tracking_number);
    }

    public function test_refund_request_is_owned_and_eligibility_and_amount_are_server_controlled(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $pending = $this->place($customer, Product::firstOrFail());
        $this->post(route('account.refunds.store', $pending), ['reason' => 'The device does not work correctly.'])
            ->assertSessionHasErrors('refund');
        $this->actingAs($other)->post(route('account.refunds.store', $pending), [
            'reason' => 'The device does not work correctly.',
        ])->assertNotFound();
        $this->actingAs($admin)->post(route('admin.orders.confirm-payment', $pending))->assertRedirect();
        $this->patch(route('admin.orders.update', $pending), ['status' => 'completed'])->assertRedirect();
        $refund = $this->requestRefund($customer, $pending);
        $this->assertEquals($pending->fresh()->total, (float) $refund->amount);
        $this->assertSame($customer->id, $refund->user_id);
        $this->post(route('account.refunds.store', $pending), ['reason' => 'The device does not work correctly.'])
            ->assertSessionHasErrors('refund');
        $this->actingAs($other)->get(route('account.refunds'))->assertOk()->assertDontSee($pending->reference);
    }

    public function test_refund_window_and_amount_limit_are_enforced(): void
    {
        config()->set('mobile-arena.refund.window_days', 7);
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->completePickup($customer, $admin, Product::firstOrFail());
        $this->travel(8)->days();
        $this->actingAs($customer)->post(route('account.refunds.store', $order), [
            'reason' => 'The device does not work correctly.',
        ])->assertSessionHasErrors('refund');
        $this->travelBack();
        $refund = $this->requestRefund($customer, $order);
        $this->actingAs($admin)->patch(route('admin.refunds.review', $refund), [
            'status' => 'approved', 'amount' => $order->total + 1,
        ])->assertSessionHasErrors('amount');
        $this->assertSame('requested', $refund->fresh()->status);
    }

    public function test_staff_can_approve_and_complete_sellable_refund_only_once(): void
    {
        $product = Product::firstOrFail();
        $initialStock = $product->stock;
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->completePickup($customer, $admin, $product);
        $refund = $this->requestRefund($customer, $order);
        $this->actingAs($customer)->patch(route('admin.refunds.review', $refund), ['status' => 'approved'])->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.refunds.review', $refund), ['status' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $refund->fresh()->status);
        $this->post(route('admin.refunds.complete', $refund), ['return_disposition' => 'sellable'])->assertRedirect();
        $this->assertSame('refunded', $refund->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame($initialStock, $product->fresh()->stock);
        $this->assertSame(1, InventoryMovement::where('type', 'refund_return')->count());
        $this->post(route('admin.refunds.complete', $refund), ['return_disposition' => 'sellable'])->assertRedirect();
        $this->assertSame($initialStock, $product->fresh()->stock);
        $this->assertSame(1, InventoryMovement::where('type', 'refund_return')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'refund.approved', 'auditable_id' => $refund->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'refund.completed', 'auditable_id' => $refund->id]);
    }

    public function test_damaged_refund_does_not_restore_sellable_stock_and_rejection_is_terminal(): void
    {
        $product = Product::firstOrFail();
        $initialStock = $product->stock;
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->completePickup($customer, $admin, $product);
        $refund = $this->requestRefund($customer, $order);
        $this->actingAs($admin)->patch(route('admin.refunds.review', $refund), ['status' => 'rejected'])->assertRedirect();
        $this->patch(route('admin.refunds.review', $refund), ['status' => 'approved'])->assertSessionHasErrors('status');
        $this->assertSame('rejected', $refund->fresh()->status);

        $another = $this->completePickup($customer, $admin, $product);
        $damaged = $this->requestRefund($customer, $another);
        $this->actingAs($admin)->patch(route('admin.refunds.review', $damaged), ['status' => 'approved'])->assertRedirect();
        $this->post(route('admin.refunds.complete', $damaged), ['return_disposition' => 'damaged'])->assertRedirect();
        $this->assertSame($initialStock - 2, $product->fresh()->stock);
        $this->assertSame(0, InventoryMovement::where('type', 'refund_return')->count());
        $this->assertSame(1, InventoryMovement::where('type', 'damage')->count());
    }

    public function test_review_notes_can_be_revised_with_an_audit_entry(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->completePickup($customer, $admin, $product);
        $refund = $this->requestRefund($customer, $order);
        $this->actingAs($admin)->patch(route('admin.refunds.review', $refund), [
            'status' => 'under_review', 'staff_notes' => 'Please bring the original receipt.',
        ])->assertRedirect();
        $this->patch(route('admin.refunds.review', $refund), [
            'status' => 'under_review', 'staff_notes' => 'Receipt and accessories requested.',
        ])->assertRedirect();
        $this->assertSame('Receipt and accessories requested.', $refund->fresh()->staff_notes);
        $this->assertSame(1, AuditLog::where('action', 'refund.notes_updated')->count());
        $this->assertSame(1, $customer->notifications()->where('data', 'like', '%refund.review_updated%')->count());
    }

    public function test_audit_log_whitelist_discards_sensitive_fields(): void
    {
        $order = $this->place(User::factory()->create(), Product::firstOrFail());
        app(\App\Services\AuditTrail::class)->record(null, 'order.test_safe_fields', $order,
            ['password' => 'SECRET', 'payment_reference' => 'SECRET', 'status' => 'processing'],
            ['password' => 'SECRET', 'payment_reference' => 'SECRET', 'status' => 'completed']);
        $log = AuditLog::where('action', 'order.test_safe_fields')->sole();
        $this->assertSame(['status' => 'processing'], $log->old_values);
        $this->assertSame(['status' => 'completed'], $log->new_values);
    }

    public function test_repair_changes_create_history_audit_and_customer_notification(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($customer)->post(route('repair.store'), [
            'customer_name' => $customer->name, 'phone' => '09171234567', 'email' => $customer->email,
            'device_type' => 'phone', 'brand_model' => 'Demo handset', 'issue' => 'Screen has a crack',
            'service_type' => 'repair',
        ])->assertOk();
        $repair = RepairBooking::sole();
        $this->actingAs($admin)->patch(route('admin.repairs.update', $repair), [
            'status' => 'confirmed', 'estimated_cost' => 1200, 'technician_notes' => 'Replacement screen available',
        ])->assertRedirect();
        $this->assertSame(2, $repair->statusEvents()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'repair.status_changed', 'auditable_id' => $repair->id]);
        $this->assertSame(1, $customer->notifications()->where('data', 'like', '%repair.confirmed%')->count());
        $this->actingAs($customer)->get(route('account.repairs'))->assertOk()->assertSee('Replacement screen available');
    }

    public function test_notifications_are_owned_readable_and_not_duplicated_by_repeat_transition(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->place($customer, Product::firstOrFail());
        $this->assertSame(1, $customer->notifications()->count());
        $this->actingAs($admin)->post(route('admin.orders.confirm-payment', $order))->assertRedirect();
        $this->patch(route('admin.orders.update', $order), ['status' => 'completed'])->assertRedirect();
        $count = $customer->notifications()->count();
        $this->patch(route('admin.orders.update', $order), ['status' => 'completed'])->assertRedirect();
        $this->assertSame($count, $customer->notifications()->count());
        $notice = $customer->notifications()->firstOrFail();
        $this->actingAs($other)->post(route('account.notifications.read', $notice->id))->assertNotFound();
        $this->get(route('account.notifications'))->assertOk()->assertDontSee($order->reference);
        $this->actingAs($customer)->get(route('account.notifications'))->assertOk()->assertSee($order->reference);
        $this->post(route('account.notifications.read', $notice->id))->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);
        $this->post(route('account.notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $customer->unreadNotifications()->count());
    }

    public function test_expiration_audits_notifies_and_releases_once(): void
    {
        $product = Product::firstOrFail();
        $customer = User::factory()->create();
        $order = $this->place($customer, $product, 'gcash_manual');
        $this->travel(31)->minutes();
        $this->artisan('orders:expire-reservations')->assertSuccessful();
        $this->artisan('orders:expire-reservations')->assertSuccessful();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, $product->fresh()->reserved_stock);
        $this->assertSame(1, InventoryMovement::where('type', 'reservation_release')->count());
        $this->assertSame(1, AuditLog::where('action', 'order.reservation_expired')->count());
        $this->assertSame(1, $customer->notifications()->where('data', 'like', '%order.cancelled%')->count());
    }

    public function test_customer_cannot_open_staff_audit_refund_or_inventory_history(): void
    {
        $customer = User::factory()->create();
        $product = Product::firstOrFail();
        $this->actingAs($customer)->get(route('admin.audit'))->assertForbidden();
        $this->get(route('admin.refunds.index'))->assertForbidden();
        $this->get(route('admin.products.history', $product))->assertForbidden();
        $order = $this->place($customer, $product);
        $this->get(route('admin.orders.show', $order))->assertForbidden();
    }
}
