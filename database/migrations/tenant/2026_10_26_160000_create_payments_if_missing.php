<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('payments')) return; // Existing data/schema must be preserved.
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->nullable()->index();
            $t->foreignId('booking_id')->nullable()->index();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('user_name')->nullable();
            $t->foreignId('customer_id')->nullable()->index();
            $t->foreignId('customer_company_id')->nullable()->index();
            $t->foreignId('lawsuit_id')->nullable()->index();
            $t->foreignId('sold_car_id')->nullable()->index();
            $t->foreignId('bank_id')->nullable()->index();
            $t->enum('payment_for', ['contract', 'booking', 'company', 'lawsuits', 'sold car']);
            // A string permits 'check' in addition to cash and credit_card.
            $t->string('payment_type', 30);
            $t->boolean('is_bank_transferee')->default(false);
            $t->date('date');
            $t->decimal('payment_value', 14, 2);
            $t->decimal('company_value', 14, 2);
            $t->string('check_bank')->nullable();
            $t->string('check_no')->nullable();
            $t->date('check_due_date')->nullable();
            $t->string('auth_number')->nullable();
            $t->string('credit_card_number')->nullable();
            $t->timestamps();
            $t->index(['payment_for', 'date']);
        });
    }
    public function down(): void
    { /* Safe no-op: don't drop a table with financial transactions. */
    }
};
