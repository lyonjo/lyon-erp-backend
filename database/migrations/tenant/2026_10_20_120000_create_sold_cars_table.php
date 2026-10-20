<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sold_cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->unique()->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('buyer_name', 200);
            $table->string('phone', 40);
            $table->decimal('price', 14, 2);
            $table->boolean('tax')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sold_cars');
    }
};
