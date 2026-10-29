<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['customers', 'customer_companies'] as $table) {
            if (!Schema::hasColumn($table, 'note')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->text('note')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['customers', 'customer_companies'] as $table) {
            if (Schema::hasColumn($table, 'note')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('note');
                });
            }
        }
    }
};
