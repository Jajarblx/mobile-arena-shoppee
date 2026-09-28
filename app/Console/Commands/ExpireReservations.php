<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderLifecycle;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'orders:expire-reservations';

    protected $description = 'Cancel unpaid expired orders and release their reserved stock';

    public function handle(OrderLifecycle $lifecycle): int
    {
        $count = 0;
        Order::where('status', 'pending_payment')
            ->where('stock_state', 'reserved')
            ->whereIn('payment_status', ['unpaid', 'failed', 'pending_verification'])
            ->where('reservation_expires_at', '<=', now())
            ->orderBy('id')->chunkById(100, function ($orders) use ($lifecycle, &$count) {
                foreach ($orders as $order) {
                    if ($lifecycle->expire($order)) {
                        $count++;
                    }
                }
            });
        $this->info("Expired {$count} reservation(s).");

        return self::SUCCESS;
    }
}
