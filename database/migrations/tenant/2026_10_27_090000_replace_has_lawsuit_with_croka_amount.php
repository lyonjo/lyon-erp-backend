<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('extra_money', 'croka_amount')) {
            Schema::table('extra_money', fn(Blueprint $t) => $t->decimal('croka_amount', 12, 2)->default(0)->after('croka_number'));
        }
        // Convert any legacy Croka service selections into the dedicated Croka charge.
        $crokaIds = DB::table('extra_money_services')->whereRaw('LOWER(TRIM(name)) = ?', ['croka'])->pluck('id');
        foreach ($crokaIds as $id) {
            $rows = DB::table('extra_money_extra_money_service')->where('extra_money_service_id', $id)->get();
            foreach ($rows as $row) {
                DB::table('extra_money')->where('id', $row->extra_money_id)->update([
                    'has_croka' => true,
                    'croka_amount' => DB::raw('croka_amount + ' . (float)$row->price),
                ]);
                DB::table('extra_money_extra_money_service')->where('id', $row->id)->delete();
            }
        }
        // Other services retain historical pivot prices. Croka is independent of the service catalog.
        if (Schema::hasColumn('extra_money_services', 'has_lawsuit')) {
            Schema::table('extra_money_services', fn(Blueprint $t) => $t->dropColumn('has_lawsuit'));
        }
    }
    public function down(): void
    {
        if (!Schema::hasColumn('extra_money_services', 'has_lawsuit')) Schema::table('extra_money_services', fn(Blueprint $t) => $t->boolean('has_lawsuit')->default(false));
        if (Schema::hasColumn('extra_money', 'croka_amount')) Schema::table('extra_money', fn(Blueprint $t) => $t->dropColumn('croka_amount'));
    }
};
