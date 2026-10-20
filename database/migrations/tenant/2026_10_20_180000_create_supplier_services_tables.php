<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name');
            $table->string('country_code', 12)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('representative_name')->nullable();
            $table->string('representative_country_code', 12)->nullable();
            $table->string('representative_phone', 30)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::create('service_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->unique(['supplier_id', 'service_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('service_supplier');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('services');
    }
};
