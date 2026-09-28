<?php

namespace App\Http\Controllers;

use App\Support\MobileNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('account.index', [
            'user' => $user,
            'ordersCount' => $user->orders()->count(),
            'repairsCount' => $user->repairBookings()->count(),
            'wishlistCount' => $user->wishlistProducts()->count(),
        ]);
    }

    public function edit(Request $request)
    {
        return view('account.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'mobile' => MobileNumber::normalize((string) $request->input('mobile')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'mobile' => ['required', 'regex:/^\+?[0-9]{10,15}$/', Rule::unique('users', 'mobile')->ignore($user->id)],
            'current_password' => ['required_with:password', 'nullable', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user->fill(collect($validated)->only(['name', 'email', 'mobile'])->all());
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        return redirect()->route('account.index')->with('success', 'Your profile has been updated.');
    }

    public function purchases(Request $request)
    {
        $filters = [
            'all' => ['label' => 'All', 'statuses' => []],
            'to_pay' => ['label' => 'To Pay', 'statuses' => ['pending', 'pending_payment']],
            'to_ship' => ['label' => 'To Ship', 'statuses' => ['processing', 'to_ship']],
            'to_receive' => ['label' => 'To Receive', 'statuses' => ['shipped']],
            'completed' => ['label' => 'Completed', 'statuses' => ['completed']],
            'cancelled' => ['label' => 'Cancelled', 'statuses' => ['cancelled']],
        ];
        $activeFilter = $request->query('status', 'all');
        abort_unless(is_string($activeFilter) && array_key_exists($activeFilter, $filters), 404);

        $query = $request->user()->orders()->with('items.product', 'items.review')->latest();
        if ($activeFilter !== 'all') {
            $query->whereIn('status', $filters[$activeFilter]['statuses']);
        }

        return view('account.purchases', [
            'orders' => $query->paginate(10)->withQueryString(),
            'filters' => $filters,
            'activeFilter' => $activeFilter,
        ]);
    }

    public function repairs(Request $request)
    {
        return view('account.repairs', ['repairs' => $request->user()->repairBookings()
            ->with(['statusEvents' => fn ($query) => $query->orderBy('created_at')->orderBy('id')])
            ->latest()->paginate(10)]);
    }

    public function wishlist(Request $request)
    {
        return view('account.wishlist', ['products' => $request->user()->wishlistProducts()->with('category')
            ->withCount('publishedReviews as reviews_count')->withAvg('publishedReviews as average_rating', 'rating')
            ->latest('wishlists.created_at')->paginate(12)]);
    }
}
