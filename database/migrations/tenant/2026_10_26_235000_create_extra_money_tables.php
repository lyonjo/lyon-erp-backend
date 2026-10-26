<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('extra_money_services', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->decimal('price', 12, 2)->default(0);
            $t->boolean('has_lawsuit')->default(false);
            $t->timestamps();
        });
        Schema::create('extra_money', function (Blueprint $t) {
            $t->id();
            $t->foreignId('car_id')->constrained('vehicles')->restrictOnDelete();
            $t->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $t->date('date');
            $t->time('time');
            $t->boolean('has_croka')->default(false);
            $t->boolean('is_responsible')->nullable();
            $t->string('croka_number')->nullable();
            $t->decimal('value', 12, 2)->default(0);
            $t->string('manual_customer_name')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->index(['car_id', 'date', 'time']);
        });
        Schema::create('extra_money_extra_money_service', function (Blueprint $t) {
            $t->id();
            $t->foreignId('extra_money_id')->constrained('extra_money')->cascadeOnDelete();
            $t->foreignId('extra_money_service_id')->constrained('extra_money_services')->restrictOnDelete();
            $t->decimal('price', 12, 2);
            $t->unique(['extra_money_id', 'extra_money_service_id'], 'em_service_unique');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('extra_money_extra_money_service');
        Schema::dropIfExists('extra_money');
        Schema::dropIfExists('extra_money_services');
    }
};
