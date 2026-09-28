<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CustomerNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RepairController extends Controller
{
    public function create()
    {
        return view('repair.create');
    }

    public function store(Request $request, CustomerNotifier $notifier)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'device_type' => ['required', 'in:phone,tablet,laptop,other'],
            'brand_model' => ['required', 'string', 'max:150'],
            'issue' => ['required', 'string', 'max:1200'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'service_type' => ['required', 'in:diagnostic,repair,refurbishment'],
        ]);

        $booking = DB::transaction(function () use ($request, $validated, $notifier) {
            $booking = $request->user()->repairBookings()->create([
                ...$validated,
                'reference' => 'REP-' . now()->format('ymd') . '-' . strtoupper(Str::random(5)),
                'status' => 'pending',
            ]);
            $booking->statusEvents()->create(['status' => 'pending', 'note' => 'Repair request placed']);
            $notifier->admins('admin.repair_placed', 'New repair request',
                $booking->reference.' is ready for review.', route('admin.dashboard'));

            return $booking;
        }, 3);

        return view('repair.success', compact('booking'));
    }
}
