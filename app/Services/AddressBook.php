<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddressBook
{
    public function create(User $user, array $data, bool $makeDefault = false): Address
    {
        return DB::transaction(function () use ($user, $data, $makeDefault) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $shouldDefault = $makeDefault || ! $user->addresses()->exists();
            if ($shouldDefault) {
                $user->addresses()->where('is_default', true)->update(['is_default' => false]);
            }

            return $user->addresses()->create([...$data, 'is_default' => $shouldDefault]);
        });
    }

    public function setDefault(User $user, Address $address): void
    {
        abort_unless($address->user_id === $user->id, 404);
        DB::transaction(function () use ($user, $address) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->addresses()->where('is_default', true)->update(['is_default' => false]);
            $user->addresses()->whereKey($address->id)->update(['is_default' => true]);
        });
    }

    public function delete(User $user, Address $address): void
    {
        abort_unless($address->user_id === $user->id, 404);
        DB::transaction(function () use ($user, $address) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $current = $user->addresses()->whereKey($address->id)->firstOrFail();
            $wasDefault = $current->is_default;
            $current->delete();
            if ($wasDefault) {
                $user->addresses()->oldest()->first()?->update(['is_default' => true]);
            }
        });
    }
}
