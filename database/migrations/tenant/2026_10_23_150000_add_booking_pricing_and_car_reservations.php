<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('bookings', 'car_rental_price_id')) Schema::table('bookings', fn(Blueprint $t) => $t->foreignId('car_rental_price_id')->nullable()->index());
        if (Schema::hasColumn('bookings', 'car_id')) Schema::table('bookings', fn(Blueprint $t) => $t->unsignedBigInteger('car_id')->nullable()->change());
        if (!Schema::hasTable('car_reservations')) Schema::create('car_reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $t->foreignId('car_id')->constrained('vehicles')->restrictOnDelete();
            $t->dateTime('pickup_start_datetime');
            $t->dateTime('dropoff_end_datetime');
            $t->timestamps();
            $t->unique('booking_id');
            $t->index(['car_id', 'pickup_start_datetime', 'dropoff_end_datetime'], 'reservation_car_time_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('car_reservations');
        if (Schema::hasColumn('bookings', 'car_rental_price_id')) Schema::table('bookings', fn(Blueprint $t) => $t->dropColumn('car_rental_price_id'));
    }
};
