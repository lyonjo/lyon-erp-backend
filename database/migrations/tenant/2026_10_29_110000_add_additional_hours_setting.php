<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->insertOrIgnore([
            'key' => 'additional_hours',
            'value' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'additional_hours')->delete();
        }
    }
};
