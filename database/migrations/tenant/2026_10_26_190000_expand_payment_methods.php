<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'payment_type')) return;
        if (DB::getDriverName() !== 'mysql') return;
        $column = DB::selectOne("SHOW COLUMNS FROM `payments` LIKE 'payment_type'");
        if (!$column || !str_starts_with(strtolower((string) $column->Type), 'enum(')) return;
        // Preserve the existing nullability and default before changing the enum to a string.
        $nullable = strtoupper((string) $column->Null) === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $column->Default === null ? '' : ' DEFAULT ' . DB::getPdo()->quote((string) $column->Default);
        DB::statement("ALTER TABLE `payments` MODIFY COLUMN `payment_type` VARCHAR(30) {$nullable}{$default}");
    }
    public function down(): void
    { /* Payment history must not be truncated or restricted on rollback. */
    }
};
