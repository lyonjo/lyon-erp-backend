<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{CarRentalPrice, CustomerCompany};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

class CompanyCarRentalPriceController extends Controller
{
    /** Only customer companies, never the rental fleet's companies table. */
    public function companies(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => 'nullable|string|max:150']);
        $nameColumn = Schema::hasColumn('customer_companies', 'name') ? 'name' : 'company_name';
        $query = CustomerCompany::query()->select('id')->selectRaw("`{$nameColumn}` as name")->orderBy($nameColumn);
        if (!empty($data['search'])) $query->where($nameColumn, 'like', '%' . $data['search'] . '%');
        return response()->json(['data' => $query->limit(300)->get()]);
    }

    /** One row per BRAND/MODEL/YEAR in the customer catalogue; company overrides are optional. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|integer|exists:customer_companies,id',
            'search' => 'nullable|string|max:150',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|between:1,100',
        ]);
        $companyId = (int) $data['company_id'];
        $query = CarRentalPrice::query()
            ->with(['brand:id,name', 'model:id,name', 'images'])
            ->where('client_type', 'customer')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')->from('car_rental_prices as newer')
                    ->where('newer.client_type', 'customer')
                    ->whereColumn('newer.brand_id', 'car_rental_prices.brand_id')
                    ->whereColumn('newer.model_id', 'car_rental_prices.model_id')
                    ->whereColumn('newer.model_year', 'car_rental_prices.model_year')
                    ->whereColumn('newer.id', '>', 'car_rental_prices.id');
            });
        if (!empty($data['search'])) {
            $term = '%' . $data['search'] . '%';
            $query->where(fn($q) => $q
                ->whereHas('brand', fn($b) => $b->where('name', 'like', $term))
                ->orWhereHas('model', fn($m) => $m->where('name', 'like', $term))
                ->orWhere('model_year', 'like', $term));
        }
        $page = $query->orderByDesc('id')->paginate($data['per_page'] ?? 10);
        $baseRows = $page->getCollection();
        $overrides = $baseRows->isEmpty() ? collect() : CarRentalPrice::query()->where('client_type', 'company')
            ->where('company_id', $companyId)
            ->where(function ($q) use ($baseRows) {
                foreach ($baseRows as $base) {
                    $q->orWhere(fn($x) => $x->where('brand_id', $base->brand_id)
                        ->where('model_id', $base->model_id)
                        ->where('model_year', $base->model_year));
                }
            })->orderByDesc('id')->get();
        $bySpecification = [];
        foreach ($overrides as $price) {
            $key = $price->brand_id . '-' . $price->model_id . '-' . $price->model_year;
            $bySpecification[$key] ??= $price;
        }
        $page->getCollection()->transform(function ($base) use ($bySpecification) {
            $key = $base->brand_id . '-' . $base->model_id . '-' . $base->model_year;
            $override = $bySpecification[$key] ?? null;
            return [
                'base_price_id' => $base->id,
                'company_price_id' => $override?->id,
                'brand_id' => $base->brand_id,
                'model_id' => $base->model_id,
                'model_year' => $base->model_year,
                'brand' => $base->brand,
                'model' => $base->model,
                'main_image' => $base->images->firstWhere('type', 'main'),
                'customized' => (bool) $override,
            ];
        });
        return response()->json($page);
    }

    /** Returns the company override, or a prefilling copy of customer pricing, with BASE images. */
    public function editData(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|integer|exists:customer_companies,id',
            'base_price_id' => 'required|integer|exists:car_rental_prices,id',
        ]);
        $base = CarRentalPrice::query()->with(['brand:id,name', 'model:id,name', 'periods.intervals', 'images'])
            ->where('client_type', 'customer')->findOrFail($data['base_price_id']);
        $override = $this->overrideFor($base, (int)$data['company_id']);
        $pricing = $override ? $override->load('periods.intervals') : $base;
        $company = CustomerCompany::query()->findOrFail($data['company_id']);
        $companyNameColumn = Schema::hasColumn('customer_companies', 'name') ? 'name' : 'company_name';
        return response()->json(['data' => [
            'base_price_id' => $base->id,
            'company_price_id' => $override?->id,
            'company' => ['id' => $company->id, 'name' => $company->{$companyNameColumn}],
            'brand_id' => $base->brand_id,
            'model_id' => $base->model_id,
            'model_year' => $base->model_year,
            'brand' => $base->brand,
            'model' => $base->model,
            'periods' => $pricing->periods,
            'main_image' => $base->images->firstWhere('type', 'main'),
            'gallery_images' => $base->images->where('type', 'gallery')->values(),
            'customized' => (bool)$override,
        ]]);
    }

    /** Only pricing intervals are writable. No company image upload/removal endpoints. */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|integer|exists:customer_companies,id',
            'base_price_id' => 'required|integer|exists:car_rental_prices,id',
            'periods' => 'required|array|min:1|max:100',
            'periods.*.start_date' => 'required|date_format:Y-m-d',
            'periods.*.end_date' => 'required|date_format:Y-m-d',
            'periods.*.intervals' => 'required|array|min:1|max:100',
            'periods.*.intervals.*.start' => 'required|integer|min:1',
            'periods.*.intervals.*.end' => 'required|integer|min:2',
            'periods.*.intervals.*.price' => 'required|numeric|min:0|max:9999999999.99',
        ]);
        $errors = [];
        foreach ($data['periods'] as $i => $p) {
            if ($p['start_date'] > $p['end_date']) $errors["periods.$i.end_date"] = ['End date must not precede start date.'];
            $ranges = [];
            foreach ($p['intervals'] as $j => $range) {
                $a = (int) $range['start'];
                $b = (int) $range['end'];
                if ($a >= $b) $errors["periods.$i.intervals.$j.end"] = ['End day must exceed start day.'];
                foreach ($ranges as [$x, $y]) if ($a <= $y && $b >= $x) {
                    $errors["periods.$i.intervals.$j.start"] = ['Price intervals cannot overlap.'];
                    break;
                }
                $ranges[] = [$a, $b];
            }
        }
        for ($i = 0; $i < count($data['periods']); $i++) for ($j = $i + 1; $j < count($data['periods']); $j++) {
            $a = $data['periods'][$i];
            $b = $data['periods'][$j];
            if ($a['start_date'] <= $b['end_date'] && $b['start_date'] <= $a['end_date'])
                $errors["periods.$j.start_date"] = ['Pricing periods cannot overlap.'];
        }
        if ($errors) throw ValidationException::withMessages($errors);
        $base = CarRentalPrice::query()->where('client_type', 'customer')->findOrFail($data['base_price_id']);
        // Serialize modifications for a given company/specification, avoiding duplicate overrides in normal concurrent requests.
        $price = DB::transaction(function () use ($base, $data) {
            $companyId = (int)$data['company_id'];
            CustomerCompany::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            $existing = $this->overrideFor($base, $companyId);
            $price = $existing ?: CarRentalPrice::create([
                'brand_id' => $base->brand_id,
                'model_id' => $base->model_id,
                'model_year' => $base->model_year,
                'client_type' => 'company',
                'company_id' => $companyId,
            ]);
            $price->periods()->delete();
            foreach ($data['periods'] as $p) {
                $period = $price->periods()->create(['start_date' => $p['start_date'], 'end_date' => $p['end_date']]);
                foreach ($p['intervals'] as $r) $period->intervals()->create([
                    'start' => $r['start'],
                    'end' => $r['end'],
                    'price' => $r['price'],
                ]);
            }
            return $price;
        });
        return response()->json(['data' => ['company_price_id' => $price->id, 'base_price_id' => $base->id, 'customized' => true]]);
    }

    private function overrideFor(CarRentalPrice $base, int $companyId): ?CarRentalPrice
    {
        return CarRentalPrice::query()->where('client_type', 'company')->where('company_id', $companyId)
            ->where('brand_id', $base->brand_id)->where('model_id', $base->model_id)
            ->where('model_year', $base->model_year)->orderByDesc('id')->first();
    }
}
