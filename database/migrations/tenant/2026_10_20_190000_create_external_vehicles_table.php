<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('external_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('number', 100)->unique();
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->foreignId('model_id')->constrained('models')->restrictOnDelete();
            $table->unsignedSmallInteger('model_year');
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('vin_number', 100)->nullable();
            $table->string('engine_number', 100)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->enum('fuel_type', ['gasoline', 'diesel', 'electric', 'hyprid']);
            $table->string('engine_size', 100)->nullable();
            $table->unsignedTinyInteger('passenger_number');
            $table->enum('gearbox_type', ['manual', 'automatic']);
            $table->boolean('camera')->default(false);
            $table->boolean('bluetooth')->default(false);
            $table->date('license_expiration')->nullable();
            $table->date('insurance_expiration')->nullable();
            $table->unsignedBigInteger('current_km')->default(0);
            $table->unsignedBigInteger('odometer')->nullable();
            $table->boolean('status')->default(true);
            $table->string('current_location')->nullable();
            $table->timestamps();
            $table->index(['supplier_id', 'brand_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('external_vehicles'); }
};
