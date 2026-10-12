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
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('module');                       // rent | accounting
            $table->string('plan');                         // trial | annual | own
            $table->string('status')->default('active');    // active | suspended | expired
            $table->string('external_order_id')->nullable()->unique();
            $table->timestamp('ends_at')->nullable();       // null for "own"
            $table->timestamps();

            $table->unique(['tenant_id', 'module']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_modules');
    }
};
