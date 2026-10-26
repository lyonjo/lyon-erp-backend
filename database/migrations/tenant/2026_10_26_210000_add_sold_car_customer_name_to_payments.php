<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payments') || Schema::hasColumn('payments', 'sold_car_customer_name')) {
            return;
        }
        Schema::table('payments', function (Blueprint $table) {
            $table->string('sold_car_customer_name', 190)->nullable()->after('sold_car_id');
        });
    }

    public function down(): void
    {
        // Financial records must not lose recorded customer names during rollback.
    }
};
