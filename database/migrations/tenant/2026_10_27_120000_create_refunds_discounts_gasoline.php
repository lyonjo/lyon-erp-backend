<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('refunds')) Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->nullable()->index();
            $t->unsignedBigInteger('transportation_id')->nullable()->index();
            $t->decimal('amount', 12, 2);
            $t->date('date');
            $t->string('company_name')->nullable();
            $t->timestamps();
        });
        if (!Schema::hasTable('discounts')) Schema::create('discounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->nullable()->index();
            $t->unsignedBigInteger('transportation_id')->nullable()->index();
            $t->decimal('amount', 12, 2);
            $t->boolean('is_approved')->default(false);
            $t->json('approved_by')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->text('note')->nullable();
            $t->string('company_name')->nullable();
            $t->timestamps();
        });
        if (!Schema::hasTable('gasoline')) Schema::create('gasoline', function (Blueprint $t) {
            $t->id();
            $t->foreignId('car_id')->index();
            $t->foreignId('contract_id')->nullable()->index();
            $t->unsignedBigInteger('transportation_id')->nullable()->index();
            $t->date('date');
            $t->decimal('amount', 12, 2);
            $t->string('company_name')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('gasoline');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('refunds');
    }
};
