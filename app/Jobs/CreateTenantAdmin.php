<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class CreateTenantAdmin
{
    public function __construct(protected TenantWithDatabase $tenant) {}

    public function handle(): void
    {
        $t    = $this->tenant;
        $hash = $t->admin_password_hash ?: Hash::make(Str::random(32));

        $t->run(function () use ($t, $hash) {
            $user = User::firstOrCreate(
                ['email' => $t->admin_email],
                ['name' => $t->admin_name, 'password' => Str::random(40)]
            );

            // Write the Store hash directly. The "hashed" cast could hash an existing hash again.
            DB::table('users')->where('id', $user->id)->update([
                'password' => $hash,
                'phone'    => $t->admin_phone,
            ]);

            $user->assignRole('Admin');
        });

        // The hash must not stay in the central database
        $t->admin_password_hash = null;
        $t->save();
    }
}
