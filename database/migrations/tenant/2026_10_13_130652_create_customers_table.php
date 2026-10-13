<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\ClientSide;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->enum('client_side', array_column(ClientSide::cases(), 'value'))
                ->default(ClientSide::LocalCustomer->value)
                ->index();       // App\Enums\ClientSide
            $table->string('country_code', 8)->nullable();   // e.g. +962
            $table->string('phone_number', 32)->nullable()->index();
            $table->text('address')->nullable();
            $table->boolean('is_multi_contract')->default(false);
            $table->boolean('is_blacklist')->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
