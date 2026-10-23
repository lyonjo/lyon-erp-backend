<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('bookings') || !Schema::hasColumn('bookings', 'status')) return;
        if (DB::getDriverName() !== 'mysql') return;
        $rows = DB::select("SHOW COLUMNS FROM `bookings` LIKE 'status'");
        if (!$rows) return;
        $column = $rows[0];
        $type = (string)$column->Type;
        if (!str_starts_with(strtolower($type), 'enum(')) return; // VARCHAR needs no schema change
        if (str_contains(strtolower($type), "'rejected'")) return;
        $newType = substr($type, 0, -1) . ",'rejected')";
        $null = $column->Null === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $column->Default === null ? '' : " DEFAULT " . DB::getPdo()->quote((string)$column->Default);
        DB::statement("ALTER TABLE `bookings` MODIFY COLUMN `status` {$newType} {$null}{$default}");
    }
    public function down(): void
    {
        // Intentionally not removing enum value: rejected rows could already exist.
    }
};
