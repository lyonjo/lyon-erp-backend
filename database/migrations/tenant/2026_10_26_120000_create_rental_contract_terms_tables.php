<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('rental_contract_terms')) {
            Schema::create('rental_contract_terms', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
                $table->longText('paragraph_en')->nullable();
                $table->longText('paragraph_ar')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('rental_contract_term_clauses')) {
            Schema::create('rental_contract_term_clauses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rental_contract_terms_id')->constrained('rental_contract_terms')->cascadeOnDelete();
                $table->unsignedInteger('sort_order');
                $table->text('text_en');
                $table->text('text_ar');
                $table->timestamps();
                $table->unique(['rental_contract_terms_id', 'sort_order'], 'rental_terms_order_unique');
            });
        }
        // One terms document per tenant. Its fixed primary key makes duplicates impossible.
        \Illuminate\Support\Facades\DB::table('rental_contract_terms')->insertOrIgnore([
            'id' => 1,
            'paragraph_en' => '',
            'paragraph_ar' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_contract_term_clauses');
        Schema::dropIfExists('rental_contract_terms');
    }
};
