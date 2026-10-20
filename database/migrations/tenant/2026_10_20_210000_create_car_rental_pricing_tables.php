<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('car_rental_prices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $t->foreignId('model_id')->constrained('models')->restrictOnDelete();
            $t->unsignedSmallInteger('model_year');
            $t->enum('client_type', ['customer', 'company'])->default('customer');
            $t->foreignId('company_id')->nullable()->constrained('customer_companies')->restrictOnDelete();
            $t->timestamps();
            $t->index(['brand_id', 'model_id', 'model_year'], 'car_rental_prices_brand_model_year_idx');
        });
        Schema::create('pricing_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('car_rental_price_id')->constrained('car_rental_prices')->cascadeOnDelete();
            $t->date('start_date');
            $t->date('end_date');
            $t->timestamps();
            $t->index(['car_rental_price_id', 'start_date', 'end_date'], 'rental_period_lookup');
        });
        Schema::create('price_intervals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pricing_period_id')->constrained('pricing_periods')->cascadeOnDelete();
            $t->unsignedInteger('start');
            $t->unsignedInteger('end');
            $t->decimal('price', 12, 2);
            $t->timestamps();
            $t->index(['pricing_period_id', 'start', 'end'], 'price_interval_lookup');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('price_intervals');
        Schema::dropIfExists('pricing_periods');
        Schema::dropIfExists('car_rental_prices');
    }
};
