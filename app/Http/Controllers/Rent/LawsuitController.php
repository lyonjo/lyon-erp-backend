<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{Lawsuit, Company, Customer, CustomerCompany, Supplier, Driver, RentalContract};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class LawsuitController extends Controller
{
  private function relations(): array
  {
    return ['customer:id,name', 'customerCompany:id,name', 'company:id,name', 'lawyer:id,supplier_name', 'driver:id,name', 'sessions'];
  }
  private function present(Lawsuit $l): array
  {
    return array_merge($l->toArray(), [
      'customer_display_name' => $l->customer?->name ?? $l->customerCompany?->name ?? $l->customer_name ?? '—',
      'company_name' => $l->company?->name,
      'lawyer_name' => $l->lawyer?->supplier_name,
    ]);
  }
  public function index(Request $request): JsonResponse
  {
    $v = $request->validate(['search' => 'nullable|string|max:150', 'per_page' => 'nullable|integer|min:1|max:100', 'page' => 'nullable|integer|min:1']);
    $q = Lawsuit::query()->with($this->relations());
    if ($s = trim((string)($v['search'] ?? ''))) {
      $like = '%' . $s . '%';
      $q->where(function ($q) use ($like) {
        $q->where('suit_number', 'like', $like)->orWhere('party', 'like', $like)->orWhere('court', 'like', $like)
          ->orWhere('vehicle_number', 'like', $like)->orWhere('customer_name', 'like', $like)
          ->orWhereHas('company', fn($r) => $r->where('name', 'like', $like))
          ->orWhereHas('customer', fn($r) => $r->where('name', 'like', $like))
          ->orWhereHas('customerCompany', fn($r) => $r->where('name', 'like', $like));
      });
    }
    $page = $q->orderByDesc('id')->paginate((int)($v['per_page'] ?? 15));
    $page->getCollection()->transform(fn($row) => $this->present($row));
    return response()->json(['data' => $page->items(), 'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()]]);
  }
  public function show(Lawsuit $lawsuit): JsonResponse
  {
    return response()->json(['data' => $this->present($lawsuit->load($this->relations()))]);
  }
  public function options(Request $request): JsonResponse
  {
    $search = trim((string)$request->query('search', ''));
    $like = '%' . $search . '%';
    $lawyers = Supplier::query()->whereHas('services', function ($q) {
      $q->whereRaw('LOWER(TRIM(name)) IN (?, ?)', ['lawyer', 'محامي']);
    })
      ->when($search !== '', fn($q) => $q->where('supplier_name', 'like', $like))
      ->orderBy('supplier_name')->limit(100)->get(['id', 'supplier_name'])->map(fn($r) => ['id' => $r->id, 'name' => $r->supplier_name]);
    $customers = Customer::query()->when($search !== '', fn($q) => $q->where('name', 'like', $like))->orderBy('name')->limit(100)->get(['id', 'name']);
    $cc = CustomerCompany::query()->when($search !== '', fn($q) => $q->where('name', 'like', $like))->orderBy('name')->limit(100)->get(['id', 'name']);
    return response()->json(['data' => [
      'lawyers' => $lawyers,
      'customers' => $customers,
      'customer_companies' => $cc,
      'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
      'drivers' => Driver::query()->orderBy('name')->limit(200)->get(['id', 'name']),
    ]]);
  }
  private function validated(Request $request): array
  {
    return $request->validate([
      'type' => ['required', Rule::in(['customer', 'ongoing_against_us'])],
      'customer_id' => 'nullable|integer|exists:customers,id',
      'customer_company_id' => 'nullable|integer|exists:customer_companies,id',
      'customer_name' => 'nullable|string|max:255',
      'company_id' => 'required|integer|exists:companies,id',
      'suit_number' => 'nullable|string|max:150',
      'party' => 'nullable|string|max:255',
      'vehicle_number' => 'nullable|string|max:100',
      'croka_number' => 'nullable|string|max:100',
      'lawyer_id' => 'nullable|integer|exists:suppliers,id',
      'driver_id' => 'nullable|integer|exists:drivers,id',
      'contract_id' => 'nullable|integer|exists:contracts,id',
      'extra_money_id' => 'nullable|integer',
      'amount' => 'required|numeric|min:0|max:9999999999.99',
      'fees' => 'required|numeric|min:0|max:9999999999.99',
      'court' => 'nullable|string|max:255',
      'date' => 'required|date_format:Y-m-d',
      'note' => 'nullable|string|max:10000',
      'sessions' => 'present|array|max:100',
      'sessions.*.date' => 'required|date_format:Y-m-d',
      'sessions.*.note' => 'nullable|string|max:5000',
    ]);
  }
  private function save(Request $request, ?Lawsuit $lawsuit): JsonResponse
  {
    $v = $this->validated($request);
    if ($v['type'] === 'customer') {
      $count = (int)!empty($v['customer_id']) + (int)!empty($v['customer_company_id']);
      if ($count !== 1) throw ValidationException::withMessages(['customer_id' => ['Choose exactly one customer or customer company.']]);
      $v['customer_name'] = null;
    } else {
      if (!trim((string)($v['customer_name'] ?? ''))) throw ValidationException::withMessages(['customer_name' => ['Customer name is required for ongoing-against-us lawsuits.']]);
      $v['customer_id'] = null;
      $v['customer_company_id'] = null;
    }
    if (!empty($v['lawyer_id'])) {
      $allowed = Supplier::whereKey($v['lawyer_id'])->whereHas('services', fn($q) => $q->whereRaw('LOWER(TRIM(name)) IN (?,?)', ['lawyer', 'محامي']))->exists();
      if (!$allowed) throw ValidationException::withMessages(['lawyer_id' => ['Select a supplier with Lawyer/محامي service.']]);
    }
    $sessions = $v['sessions'];
    unset($v['sessions']);
    $saved = DB::transaction(function () use ($lawsuit, $v, $sessions) {
      if ($lawsuit) {
        $locked = Lawsuit::whereKey($lawsuit->id)->lockForUpdate()->firstOrFail();
        if ($locked->status === 'closed') throw ValidationException::withMessages(['status' => ['Closed lawsuits cannot be edited.']]);
        $locked->update($v);
        $locked->sessions()->delete();
        $saved = $locked;
      } else {
        $saved = Lawsuit::create(array_merge($v, ['status' => 'open']));
      }
      foreach ($sessions as $s) $saved->sessions()->create($s);
      return $saved;
    });
    return response()->json(['data' => $this->present($saved->fresh()->load($this->relations()))], $lawsuit ? 200 : 201);
  }
  public function store(Request $request): JsonResponse
  {
    return $this->save($request, null);
  }
  public function update(Request $request, Lawsuit $lawsuit): JsonResponse
  {
    return $this->save($request, $lawsuit);
  }
  public function close(Lawsuit $lawsuit): JsonResponse
  {
    DB::transaction(function () use ($lawsuit) {
      $l = Lawsuit::whereKey($lawsuit->id)->lockForUpdate()->firstOrFail();
      if ($l->status !== 'open') throw ValidationException::withMessages(['status' => ['Only open lawsuits can be closed.']]);
      $l->update(['status' => 'closed']);
    });
    return response()->json(['data' => $this->present($lawsuit->fresh()->load($this->relations()))]);
  }
  public function destroy(Lawsuit $lawsuit): JsonResponse
  {
    DB::transaction(function () use ($lawsuit) {
      $l = Lawsuit::whereKey($lawsuit->id)->lockForUpdate()->firstOrFail();
      if ($l->status === 'closed') throw ValidationException::withMessages(['status' => ['Closed lawsuits cannot be deleted.']]);
      $l->delete();
    });
    return response()->json(['message' => 'Deleted']);
  }
}
