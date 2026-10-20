<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicle_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained('vehicles')->restrictOnDelete();
            // A string stores the PHP enum's backing value. New service choices
            // do not require altering a MySQL ENUM column.
            $table->string('service_key', 60);
            $table->decimal('price', 12, 2);
            $table->text('details')->nullable();
            $table->unsignedBigInteger('counter_km');
            $table->date('date');
            $table->timestamps();
            $table->index(['car_id', 'date']);
            $table->index(['service_key', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenances');
    }
};
