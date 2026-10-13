<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('nationality')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->date('date_of_birth')->nullable();

            $table->string('passport_place_of_issue')->nullable();
            $table->date('passport_date_of_issue')->nullable();
            $table->date('passport_date_of_expire')->nullable();

            $table->string('license_no')->nullable();
            $table->string('license_place_of_issue')->nullable();
            $table->date('license_date_of_issue')->nullable();
            $table->date('license_date_of_expire')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_details');
    }
};
