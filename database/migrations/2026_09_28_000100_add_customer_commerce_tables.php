<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_name', 120);
            $table->string('phone', 20);
            $table->string('region', 120);
            $table->string('province', 120);
            $table->string('city', 120);
            $table->string('barangay', 120);
            $table->string('postal_code', 4);
            $table->string('street_address', 500);
            $table->string('landmark', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_default']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('checkout_token')->nullable()->unique();
            $table->decimal('shipping_fee', 12, 2)->nullable();
            $table->index(['user_id', 'status']);
        });

        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40);
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        Schema::table('repair_bookings', function (Blueprint $table) {
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->text('technician_notes')->nullable();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('repair_bookings', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn(['estimated_cost', 'technician_notes']);
        });
        Schema::dropIfExists('order_status_events');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropUnique(['checkout_token']);
            $table->dropColumn(['checkout_token', 'shipping_fee']);
        });
        Schema::dropIfExists('addresses');
    }
};
