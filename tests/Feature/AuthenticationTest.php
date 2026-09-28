<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Maya Cruz',
            'email' => 'maya@example.test',
            'mobile' => '09171234567',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
        ], $overrides);
    }

    public function test_guest_can_register_with_hashed_password_and_customer_role(): void
    {
        $this->post(route('register.store'), $this->registration())
            ->assertRedirect(route('account.index'));

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('customer', $user->role);
        $this->assertSame('+639171234567', $user->mobile);
        $this->assertTrue(Hash::check('StrongPass1!', $user->password));
    }

    public function test_registration_rejects_duplicate_contact_and_weak_or_mismatched_password(): void
    {
        User::factory()->create(['email' => 'maya@example.test', 'mobile' => '+639171234567']);

        $this->post(route('register.store'), $this->registration(['password' => 'weak', 'password_confirmation' => 'different']))
            ->assertSessionHasErrors(['email', 'mobile', 'password']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_login_by_email_or_mobile_and_logout_work(): void
    {
        $user = User::factory()->create(['email' => 'maya@example.test', 'mobile' => '+639171234567', 'password' => 'StrongPass1!']);

        $this->post(route('login.store'), ['login' => 'maya@example.test', 'password' => 'StrongPass1!', 'remember' => '1'])
            ->assertRedirect(route('account.index'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();

        $this->post(route('login.store'), ['login' => '09171234567', 'password' => 'StrongPass1!'])
            ->assertRedirect(route('account.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_do_not_log_in(): void
    {
        User::factory()->create(['email' => 'maya@example.test', 'password' => 'StrongPass1!']);

        $this->post(route('login.store'), ['login' => 'maya@example.test', 'password' => 'WrongPass1!'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_protected_pages_redirect_guests_then_return_to_intended_page(): void
    {
        foreach (['checkout.create', 'account.index', 'account.purchases', 'account.addresses', 'account.repairs', 'account.wishlist', 'repair.create'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }

        $user = User::factory()->create(['password' => 'StrongPass1!']);
        $this->get(route('account.purchases'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'StrongPass1!'])
            ->assertRedirect(route('account.purchases'));
    }

    public function test_checkout_and_repair_posts_are_protected(): void
    {
        $this->post(route('checkout.store'), [])->assertRedirect(route('login'));
        $this->post(route('repair.store'), [])->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_admin_and_admin_can(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_profile_edit_updates_contact_details_and_password(): void
    {
        $user = User::factory()->create(['password' => 'StrongPass1!']);

        $this->actingAs($user)->patch(route('account.update'), [
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
            'mobile' => '09170001111',
            'current_password' => 'StrongPass1!',
            'password' => 'NewStrongPass2!',
            'password_confirmation' => 'NewStrongPass2!',
        ])->assertRedirect(route('account.index'));

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('+639170001111', $user->mobile);
        $this->assertTrue(Hash::check('NewStrongPass2!', $user->password));
    }

    public function test_password_reset_link_uses_laravel_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('success');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_valid_reset_token_changes_password(): void
    {
        $user = User::factory()->create(['password' => 'OldStrongPass1!']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewStrongPass2!',
            'password_confirmation' => 'NewStrongPass2!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewStrongPass2!', $user->fresh()->password));
    }

    public function test_optional_admin_seeder_uses_configured_credentials(): void
    {
        config()->set('mobile-arena.admin', [
            'name' => 'Store Manager',
            'email' => 'manager@example.test',
            'mobile' => '09175554444',
            'password' => 'AdminStrong1!',
        ]);

        $this->seed(AdminUserSeeder::class);
        $admin = User::sole();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('AdminStrong1!', $admin->password));
    }

    public function test_wishlist_is_persistent_and_private(): void
    {
        $this->seed();
        $product = Product::firstOrFail();
        $user = User::factory()->create();

        $this->post(route('wishlist.store', $product))->assertRedirect(route('login'));
        $this->actingAs($user)->post(route('wishlist.store', $product))->assertRedirect();
        $this->get(route('account.wishlist'))->assertOk()->assertSee($product->name);
        $this->assertDatabaseHas('wishlists', ['user_id' => $user->id, 'product_id' => $product->id]);
        $this->delete(route('wishlist.destroy', $product))->assertRedirect();
        $this->assertDatabaseCount('wishlists', 0);
    }
}
