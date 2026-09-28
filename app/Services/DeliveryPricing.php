<?php

namespace App\Services;

use App\Models\Address;

class DeliveryPricing
{
    public function forAddress(Address $address): array
    {
        $origin = config('mobile-arena.delivery.origin');
        $same = fn (string $field) => mb_strtolower(trim($address->{$field})) === mb_strtolower(trim($origin[$field]));

        $band = $same('city') && $same('province') ? 'local'
            : ($same('province') ? 'province' : ($same('region') ? 'regional' : 'national'));

        return ['band' => $band, 'fee' => (int) config('mobile-arena.delivery.fees.'.$band)];
    }
}
