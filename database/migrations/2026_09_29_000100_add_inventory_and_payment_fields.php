<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('reserved_stock')->default(0);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('stock_state', 20)->default('none');
            $table->timestamp('reservation_expires_at')->nullable();
            $table->string('payment_status', 30)->default('unpaid');
            $table->string('payment_reference', 120)->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->index(['status', 'stock_state', 'reservation_expires_at'], 'orders_reservation_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_reservation_expiry_idx');
            $table->dropColumn(['stock_state', 'reservation_expires_at', 'payment_status', 'payment_reference', 'payment_confirmed_at']);
        });
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('reserved_stock'));
    }
};
