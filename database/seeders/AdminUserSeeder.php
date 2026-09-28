<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\MobileNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('mobile-arena.admin');
        $admin['email'] = Str::lower(trim((string) $admin['email']));
        $admin['mobile'] = MobileNumber::normalize((string) $admin['mobile']);

        Validator::make($admin, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'mobile' => ['required', 'regex:/^\+?[0-9]{10,15}$/'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
        ])->validate();

        $existing = User::where('email', $admin['email'])->first();
        if ($existing) {
            if ($existing->role !== 'admin') {
                throw new RuntimeException('The configured admin email already belongs to a customer. Choose another email.');
            }

            return;
        }

        User::create([
            'name' => $admin['name'],
            'email' => $admin['email'],
            'mobile' => $admin['mobile'],
            'password' => $admin['password'],
            'role' => 'admin',
        ]);
    }
}
