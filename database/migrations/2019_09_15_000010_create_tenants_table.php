<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('status')->default('provisioning');
            $table->string('company_name')->nullable();
            $table->string('admin_name');
            $table->string('admin_email');
            $table->string('admin_phone')->nullable();
            $table->timestamps();
            $table->json('data')->nullable();             // keep this, stancl needs it
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
