<?php

namespace App\Support;

class AddressData
{
    public static function rules(string $prefix = '', bool $required = true): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            $prefix.'recipient_name' => [$presence, 'string', 'max:120'],
            $prefix.'phone' => [$presence, 'regex:/^\+?[0-9]{10,15}$/'],
            $prefix.'region' => [$presence, 'string', 'max:120'],
            $prefix.'province' => [$presence, 'string', 'max:120'],
            $prefix.'city' => [$presence, 'string', 'max:120'],
            $prefix.'barangay' => [$presence, 'string', 'max:120'],
            $prefix.'postal_code' => [$presence, 'regex:/^\d{4}$/'],
            $prefix.'street_address' => [$presence, 'string', 'max:500'],
            $prefix.'landmark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
