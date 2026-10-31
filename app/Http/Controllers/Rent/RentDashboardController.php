<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\Branch;
use App\Models\Rent\RentalContract;
use App\Models\Rent\Vehicle;
use App\Models\Rent\VehicleMaintenance;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RentDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $today = CarbonImmutable::today(config('app.timezone', 'Asia/Amman'));
        $lastAlertDay = $today->addDays(30)->toDateString();
        $todayString = $today->toDateString();

        // Exclude sold vehicles consistently with the Available Cars module.
        $fleet = Vehicle::query()->whereNotIn('vehicles.id', DB::table('sold_cars')->select('vehicle_id'));

        $counts = (clone $fleet)->select('branch_id')->selectRaw('COUNT(*) AS total')
            ->groupBy('branch_id')->pluck('total', 'branch_id');

        $branches = Branch::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn(Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'count' => (int) ($counts[$branch->id] ?? 0),
            ])->values();

        $selectedBranch = isset($data['branch_id'])
            ? (int) $data['branch_id']
            : (int) ($branches->first()['id'] ?? 0);

        $vehicles = $selectedBranch
            ? (clone $fleet)->where('branch_id', $selectedBranch)
            ->with(['brand:id,name', 'model:id,name'])
            ->orderByDesc('id')->get()
            ->map(fn(Vehicle $v) => [
                'id' => $v->id,
                'number' => $v->number,
                'name' => $v->model?->name,
                'brand' => $v->brand?->name,
                'model_year' => $v->model_year,
            ])->values()
            : collect();

        // Upcoming rental contract drop-offs, including today. Do not include closed contracts.
        $contracts = RentalContract::query()
            ->where('status', 'open')
            ->whereHas('booking', fn($q) => $q->whereDate('end_date', '>=', $todayString))
            ->with([
                'booking.customer:id,name',
                'booking.customerCompany:id,name',
                'booking.car:id,number,model_year,brand_id,model_id',
                'booking.car.model:id,name'
            ])
            ->get()
            ->sortBy(fn(RentalContract $c) => sprintf(
                '%s %s %010d',
                (string) ($c->booking?->end_date ?? ''),
                (string) ($c->booking?->end_time ?? ''),
                $c->id
            ))
            ->values()
            ->map(fn(RentalContract $c) => [
                'id' => $c->id,
                'contract_no' => $c->id,
                'customer_name' => $c->booking?->customer?->name ?? $c->booking?->customerCompany?->name,
                'plate_no' => $c->booking?->car?->number,
                'model' => $c->booking?->car?->model_year,
                'model_name' => $c->booking?->car?->model?->name,
                'drop_off_date' => $c->booking?->end_date,
                'drop_off_time' => $c->booking?->end_time,
            ]);

        $renewal = (clone $fleet)
            ->where(function ($q) use ($lastAlertDay) {
                $q->whereDate('license_expiration', '<=', $lastAlertDay)
                    ->orWhereDate('insurance_expiration', '<=', $lastAlertDay);
            })
            ->with(['brand:id,name', 'model:id,name'])
            ->get();

        $mapVehicle = fn(Vehicle $v, string $dateField) => [
            'id' => $v->id,
            'plate_no' => $v->number,
            'brand' => $v->brand?->name,
            'model_name' => $v->model?->name,
            'model_year' => $v->model_year,
            'expiry_date' => $v->{$dateField}?->format('Y-m-d'),
        ];

        $licenses = $renewal->filter(fn(Vehicle $v) => $v->license_expiration !== null &&
            $v->license_expiration->format('Y-m-d') <= $lastAlertDay)
            ->sortBy('license_expiration')->map(fn(Vehicle $v) => $mapVehicle($v, 'license_expiration'))->values();

        $insurances = $renewal->filter(fn(Vehicle $v) => $v->insurance_expiration !== null &&
            $v->insurance_expiration->format('Y-m-d') <= $lastAlertDay)
            ->sortBy('insurance_expiration')->map(fn(Vehicle $v) => $mapVehicle($v, 'insurance_expiration'))->values();

        // Latest oil service per car, by service date and then ID, not just the most recent maintenance of any type.
        $oil = VehicleMaintenance::query()
            ->where('service_key', 'oil_change')
            ->with(['vehicle.brand:id,name', 'vehicle.model:id,name', 'vehicle.company:id,name'])
            ->orderBy('car_id')->orderByDesc('date')->orderByDesc('id')
            ->get()->unique('car_id')
            ->filter(function (VehicleMaintenance $m) {
                return $m->vehicle !== null && $m->counter_km !== null &&
                    $m->vehicle->current_km !== null &&
                    ((int) $m->vehicle->current_km - (int) $m->counter_km) > 5000;
            })
            ->map(function (VehicleMaintenance $m) {
                $v = $m->vehicle;
                return [
                    'id' => $m->id,
                    'vehicle_id' => $v->id,
                    'plate_no' => $v->number,
                    'company' => $v->company?->name,
                    'brand' => $v->brand?->name,
                    'model_name' => $v->model?->name,
                    'model_year' => $v->model_year,
                    'service' => 'Oil Change',
                    'service_date' => $m->date?->format('Y-m-d'),
                    'maintenance_km' => (int) $m->counter_km,
                    'current_km' => (int) $v->current_km,
                    'km_since_service' => (int) $v->current_km - (int) $m->counter_km,
                ];
            })->values();

        return response()->json(['data' => [
            'branches' => $branches,
            'selected_branch_id' => $selectedBranch ?: null,
            'vehicles' => $vehicles,
            'contracts' => $contracts,
            'renewal' => ['licenses' => $licenses, 'insurances' => $insurances],
            'oil_maintenance' => $oil,
            'as_of' => $todayString,
        ]]);
    }
}
