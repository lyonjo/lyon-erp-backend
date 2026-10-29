<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('branch_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('destination_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'destination_branch_id'], 'branch_destinations_route_unique');
            $table->index('destination_branch_id');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('branch_destinations');
    }
};
