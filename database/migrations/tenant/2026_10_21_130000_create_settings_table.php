<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->string('value', 255);
            $table->timestamps();
        });

        $now = now();
        DB::table('settings')->insert([
            ['key' => 'booking_tax_percent', 'value' => '0', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'car_rental_km_daily', 'value' => '0', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'car_rental_price_per_km', 'value' => '0', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'currency', 'value' => 'JOD', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
