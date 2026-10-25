<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('contracts')) Schema::create('contracts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->unique()->constrained('bookings')->restrictOnDelete();
            $t->unsignedBigInteger('km_out');
            $t->unsignedBigInteger('km_in')->nullable();
            $t->enum('status', ['open', 'closed'])->default('open');
            $t->timestamps();
        });
        if (!Schema::hasTable('additional_drivers')) Schema::create('additional_drivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $t->string('name');
            $t->string('license_number');
            $t->date('date_of_issue');
            $t->date('expiry_date');
            $t->string('place_of_issue');
            $t->timestamps();
        });
        $column = DB::selectOne("SHOW COLUMNS FROM `bookings` LIKE 'status'");
        if ($column && str_starts_with(strtolower($column->Type), 'enum(') && !str_contains($column->Type, "'relayed'")) {
            $all = [];
            preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $column->Type, $m);
            $all = $m[1];
            $all[] = 'relayed';
            $values = implode(',', array_map(fn($v) => DB::getPdo()->quote($v), array_unique($all)));
            DB::statement("ALTER TABLE `bookings` MODIFY COLUMN `status` ENUM($values) NOT NULL DEFAULT 'pending'");
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('additional_drivers');/* Do not drop shared/existing contracts or remove enum values containing user data. */
    }
};
