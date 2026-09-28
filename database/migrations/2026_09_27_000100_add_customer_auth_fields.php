<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile', 20)->nullable()->unique();
            $table->string('role', 30)->default('customer')->index();
            $table->string('profile_photo_path')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->index();
        });

        Schema::table('repair_bookings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->index();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('orders', fn (Blueprint $table) => $table->foreign('user_id')->references('id')->on('users')->nullOnDelete());
            Schema::table('repair_bookings', fn (Blueprint $table) => $table->foreign('user_id')->references('id')->on('users')->nullOnDelete());
        }

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('repair_bookings', fn (Blueprint $table) => $table->dropForeign(['user_id']));
            Schema::table('orders', fn (Blueprint $table) => $table->dropForeign(['user_id']));
        }
        Schema::table('repair_bookings', fn (Blueprint $table) => $table->dropColumn('user_id'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('user_id'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mobile', 'role', 'profile_photo_path']);
        });
    }
};
