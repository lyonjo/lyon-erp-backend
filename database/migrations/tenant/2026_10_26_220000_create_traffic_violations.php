<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('traffic_violations')) return;
        Schema::create('traffic_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->date('violation_date');
            $table->time('violation_time');
            $table->string('violation_no', 100);
            $table->decimal('violation_value', 12, 2);
            $table->text('violation_reason')->nullable();
            $table->string('manual_customer_name', 190)->nullable();
            $table->timestamps();
            $table->index(['car_id', 'violation_date', 'violation_time'], 'traffic_violation_car_when_idx');
            $table->index('contract_id');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('traffic_violations');
    }
};
