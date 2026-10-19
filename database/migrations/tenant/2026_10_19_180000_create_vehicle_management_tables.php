<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150)->unique();
            $t->timestamps();
        });
        Schema::create('models', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $t->string('name', 150);
            $t->unique(['brand_id', 'name']);
            $t->timestamps();
        });
        Schema::create('extra_features', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150)->unique();
            $t->timestamps();
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('number', 100)->unique();
            $t->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $t->foreignId('model_id')->constrained('models')->restrictOnDelete();
            $t->unsignedSmallInteger('model_year');
            $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $t->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $t->string('vin_number', 100)->nullable();
            $t->string('engine_number', 100)->nullable();
            $t->unsignedSmallInteger('engine_size')->nullable();
            $t->string('registration_number', 100)->nullable();
            $t->enum('fuel_type', ['gasoline', 'diesel', 'electric', 'hyprid']);
            $t->unsignedTinyInteger('passengers_number');
            $t->enum('gearbox_type', ['manual', 'automatic']);
            $t->boolean('has_blutooth')->default(false);
            $t->boolean('has_camera')->default(false);
            $t->date('license_expiration')->nullable();
            $t->date('insurance_expiration')->nullable();
            $t->unsignedInteger('current_km')->default(0);
            $t->unsignedInteger('odometer')->default(0);
            $t->string('current_location', 150);
            $t->boolean('status')->default(true);
            $t->timestamps();
        });
        Schema::create('car_features', function (Blueprint $t) {
            $t->id();
            $t->foreignId('car_id')->constrained('vehicles')->cascadeOnDelete();
            $t->foreignId('feature_id')->constrained('extra_features')->restrictOnDelete();
            $t->unique(['car_id', 'feature_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('car_features');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('extra_features');
        Schema::dropIfExists('models');
        Schema::dropIfExists('brands');
    }
};
