<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $t->foreignId('customer_company_id')->nullable()->constrained('customer_companies')->restrictOnDelete();
            $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $t->foreignId('car_id')->constrained('vehicles')->restrictOnDelete();
            $t->enum('service', ['rent', 'trip', 'program'])->default('rent');
            $t->decimal('day_price', 12, 2);
            $t->decimal('day_company_price', 12, 2);
            $t->unsignedInteger('total_days');
            $t->decimal('extra_charge', 12, 2)->default(0);
            $t->decimal('extra_company_charge', 12, 2)->default(0);
            $t->decimal('extra_km_price', 12, 2)->default(0);
            $t->decimal('tax', 12, 2)->default(0);
            $t->string('currency', 3)->default('JOD');
            $t->boolean('is_relayed')->default(false);
            $t->dateTime('relayed_date')->nullable();
            $t->enum('status', ['pending', 'online_pending', 'approved', 'realyed', 'closed'])->default('pending');
            $t->string('ticket_number')->nullable();
            $t->decimal('total', 14, 2);
            $t->decimal('branch_fee', 12, 2)->default(0);
            $t->decimal('company_total', 14, 2);
            $t->date('start_date');
            $t->time('start_time');
            $t->date('end_date');
            $t->time('end_time');
            $t->string('pick_up_location');
            $t->string('return_location');
            $t->enum('payment_method', ['cash', 'online'])->default('cash');
            $t->boolean('is_taxable')->default(false);
            $t->timestamps();
            $t->index(['car_id', 'status', 'start_date', 'end_date'], 'booking_vehicle_availability_idx');
            $t->index(['status', 'id']);
        });
        Schema::create('booking_extra_item', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $t->foreignId('extra_item_id')->constrained('extra_items')->restrictOnDelete();
            $t->decimal('unit_price', 12, 2);
            $t->boolean('is_daily');
            $t->decimal('line_total', 14, 2);
            $t->unique(['booking_id', 'extra_item_id']);
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('booking_extra_item');
        Schema::dropIfExists('bookings');
    }
};
