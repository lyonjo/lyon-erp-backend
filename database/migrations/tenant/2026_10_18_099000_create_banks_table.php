<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            // No FK yet: accounts table and model have not been specified.
            $table->unsignedBigInteger('account_id')->nullable();
            $table->boolean('for_sold_car')->default(false);
            $table->boolean('for_lawsuits')->default(false);
            $table->boolean('for_companies')->default(false);
            $table->boolean('for_booking')->default(false);
            $table->boolean('for_contracts')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
