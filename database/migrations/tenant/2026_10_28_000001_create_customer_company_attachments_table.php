<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_company_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_company_id')->constrained('customer_companies')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('customer_company_attachments');
    }
};
