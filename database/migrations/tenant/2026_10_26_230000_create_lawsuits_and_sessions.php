<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lawsuits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->foreignId('customer_company_id')->nullable()->constrained('customer_companies')->nullOnDelete();
            $t->string('suit_number', 150)->nullable();
            $t->string('party')->nullable();
            $t->string('type', 40);
            $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $t->unsignedBigInteger('extra_money_id')->nullable();
            $t->string('vehicle_number', 100)->nullable();
            $t->string('croka_number', 100)->nullable();
            $t->foreignId('lawyer_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $t->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $t->decimal('amount', 12, 2)->default(0);
            $t->decimal('fees', 12, 2)->default(0);
            $t->string('court')->nullable();
            $t->date('date');
            $t->text('note')->nullable();
            $t->string('status', 20)->default('open')->index();
            $t->string('customer_name')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'date']);
        });
        Schema::create('lawsuit_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lawsuit_id')->constrained('lawsuits')->cascadeOnDelete();
            $t->date('date');
            $t->text('note')->nullable();
            $t->timestamps();
            $t->index(['lawsuit_id', 'date']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('lawsuit_sessions');
        Schema::dropIfExists('lawsuits');
    }
};
