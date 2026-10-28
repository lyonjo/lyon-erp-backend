<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_companies', function (Blueprint $table) {
            $table->string('admin_name', 150)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('customer_companies', function (Blueprint $table) {
            $table->dropColumn('admin_name');
        });
    }
};
