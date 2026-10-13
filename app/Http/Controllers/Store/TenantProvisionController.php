<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantModule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantProvisionController extends Controller
{
    public function provision(Request $r)
    {
        if ($oid = $r->input('external_order_id')) {
            if ($existing = TenantModule::with('tenant')->where('external_order_id', $oid)->first()) {
                return response()->json($this->payload($existing->tenant, $existing));
            }
        }
        $d = $r->validate([
            'external_order_id'      => 'required|string|max:100',
            'tenant_id'              => 'nullable|exists:tenants,id',
            'subdomain'              => [
                'required_without:tenant_id',
                'nullable',
                'regex:/^[a-z0-9][a-z0-9-]{2,30}$/',
                Rule::notIn(['www', 'api', 'admin', 'app', 'mail', 'store']),
                'unique:tenants,id'
            ],
            'customer'               => 'required_without:tenant_id|array',
            'customer.name'          => 'required_without:tenant_id|string|max:255',
            'customer.company'       => 'required_without:tenant_id|string|max:255',
            'customer.email'         => 'required_without:tenant_id|email',
            'customer.phone'         => 'nullable|string|max:50',
            'customer.password_hash' => ['required_without:tenant_id', 'string', 'regex:/^\$2[aby]\$\d{2}\$.{53}$/'],
            'module'                 => ['required', Rule::in(array_keys(config('modules')))],
            'plan'                   => 'required|in:trial,annual,own',
            'ends_at'                => 'required_unless:plan,own|nullable|date|after:now',
        ]);

        $tenant = isset($d['tenant_id'])
            ? Tenant::findOrFail($d['tenant_id'])
            : $this->createTenant($d);

        $module = $tenant->modules()->updateOrCreate(
            ['module' => $d['module']],
            [
                'plan'              => $d['plan'],
                'status'            => 'active',
                'ends_at'           => $d['plan'] === 'own' ? null : $d['ends_at'],
                'external_order_id' => $d['external_order_id'],
            ]
        );

        return response()->json($this->payload($tenant->fresh(), $module), 201);
    }

    private function createTenant(array $d): Tenant
    {
        try {
            $c = $d['customer'];
            $tenant = Tenant::create([
                'id' => $d['subdomain'],
                'company_name' => $c['company'],
                'admin_name' => $c['name'],
                'admin_email' => $c['email'],
                'admin_phone' => $c['phone'] ?? null,
                'admin_password_hash' => $c['password_hash'],
            ]);
            $tenant->domains()->create(['domain' => $d['subdomain'] . '.' . config('app.tenant_base_domain')]);

            return $tenant;
        } catch (\Throwable $e) {
            \DB::table('tenants')->where('id', $d['subdomain'])->delete();   // domains and modules cascade
            \DB::statement('DROP DATABASE IF EXISTS `' . config('tenancy.database.prefix') . $d['subdomain'] . '`');
            throw $e;
        }
    }

    public function show(Tenant $tenant)
    {
        return response()->json([
            'tenant_id' => $tenant->id,
            'status'    => $tenant->status,
            'domain'    => optional($tenant->domains()->first())->domain,
            'modules'   => $tenant->modules()->get(['module', 'plan', 'status', 'ends_at']),
        ]);
    }

    /** Keep the tenant admin in sync with the Store account (password, name, phone, company). */
    public function updateAdmin(Request $r, Tenant $tenant)
    {
        $d = $r->validate([
            'name'          => 'sometimes|string|max:255',
            'company'       => 'sometimes|string|max:255',
            'phone'         => 'sometimes|nullable|string|max:50',
            'password_hash' => ['sometimes', 'string', 'regex:/^\$2[aby]\$\d{2}\$.{53}$/'],
        ]);

        $email = $tenant->admin_email;

        $tenant->run(function () use ($d, $email) {
            $update = [];
            if (isset($d['name']))          $update['name'] = $d['name'];
            if (array_key_exists('phone', $d)) $update['phone'] = $d['phone'];
            if (isset($d['password_hash'])) $update['password'] = $d['password_hash'];

            if ($update) {
                \DB::table('users')->where('email', $email)->update($update);
            }
        });

        $tenant->update(array_filter([
            'company_name' => $d['company'] ?? null,
            'admin_name'   => $d['name'] ?? null,
            'admin_phone'  => $d['phone'] ?? null,
        ], fn($v) => $v !== null));

        return response()->json(['ok' => true]);
    }

    public function updateModule(Request $r, Tenant $tenant, string $module)
    {
        $row = $this->moduleRow($tenant, $module);

        $d = $r->validate([
            'plan'    => 'sometimes|in:trial,annual,own',
            'ends_at' => 'sometimes|nullable|date',
        ]);

        $plan   = $d['plan'] ?? $row->plan;
        $endsAt = $plan === 'own' ? null : ($d['ends_at'] ?? $row->ends_at);

        abort_if($plan !== 'own' && ! $endsAt, 422, 'ends_at is required unless the plan is own.');

        $row->update(['plan' => $plan, 'ends_at' => $endsAt]);

        return response()->json($this->payload($tenant, $row->fresh()));
    }

    public function suspendModule(Tenant $tenant, string $module)
    {
        $row = $this->moduleRow($tenant, $module);
        $row->update(['status' => 'suspended']);

        return response()->json($this->payload($tenant, $row));
    }

    public function activateModule(Tenant $tenant, string $module)
    {
        $row = $this->moduleRow($tenant, $module);
        abort_if(
            $row->plan !== 'own' && (! $row->ends_at || $row->ends_at->isPast()),
            422,
            'Extend ends_at first (PATCH), then activate.'
        );

        $row->update(['status' => 'active']);

        return response()->json($this->payload($tenant, $row));
    }

    public function destroyModule(Tenant $tenant, string $module)
    {
        $this->moduleRow($tenant, $module)->delete();

        return response()->json([
            'ok'           => true,
            'tenant_empty' => $tenant->modules()->count() === 0,
        ]);
    }

    /** Drops the tenant and its database. The Store calls this only when no modules are left. */
    public function destroy(Tenant $tenant)
    {
        abort_if($tenant->modules()->exists(), 409, 'Tenant still has modules.');

        // Take a database backup here before deleting in production.
        $tenant->delete();

        return response()->noContent();
    }

    private function moduleRow(Tenant $tenant, string $module): TenantModule
    {
        return $tenant->modules()->where('module', $module)->firstOrFail();
    }

    private function payload(Tenant $tenant, TenantModule $m): array
    {
        return [
            'tenant_id' => $tenant->id,
            'domain'    => optional($tenant->domains()->first())->domain,
            'module'    => $m->module,
            'plan'      => $m->plan,
            'status'    => $m->status,
            'ends_at'   => optional($m->ends_at)->toIso8601String(),
        ];
    }
}
