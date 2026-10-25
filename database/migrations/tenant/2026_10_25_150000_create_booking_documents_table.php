<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->enum('doc_type', ['license', 'id_passport', 'flight_ticket']);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'doc_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_documents');
    }
};
