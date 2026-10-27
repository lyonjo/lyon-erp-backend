<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{Booking, Customer, CustomerCompany, RentalContract, CarReservation, Vehicle, Refund, Discount, Gasoline};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use App\Models\User;

abstract class FinanceAdjustmentController extends Controller
{
  abstract protected function model(): string;
  abstract protected function kind(): string;
  protected function context(RentalContract $contract): array
  {
    $contract->loadMissing(['booking.customer', 'booking.customerCompany', 'booking.company', 'booking.car']);
    $b = $contract->booking;
    if (!$b) throw ValidationException::withMessages(['contract_id' => ['Contract has no booking.']]);
    return [
      'company_name' => $b->company?->name,
      'customer_name' => $b->customer?->name ?? $b->customerCompany?->name,
      'company_id' => $b->company_id,
      'vehicle_number' => $b->car?->number
    ];
  }
  public function index(Request $request)
  {
    $d = $request->validate(['search' => 'nullable|string|max:120', 'per_page' => 'nullable|integer|min:1|max:100']);
    $q = ($this->model())::query();
    if ($this->kind() === 'gasoline') $q->with(['car:id,number']);
    if ($s = trim((string)($d['search'] ?? ''))) $q->where(function ($w) use ($s) {
      $w->where('id', 'like', "%$s%")->orWhere('contract_id', 'like', "%$s%")->orWhere('company_name', 'like', "%$s%");
      if ($this->kind() === 'gasoline') $w->orWhereHas('car', fn($c) => $c->where('number', 'like', "%$s%"));
    });
    $rows = $q->orderByDesc('id')->paginate((int)($d['per_page'] ?? 15));
    $collection = $rows->getCollection();
    $collection->each(function ($r) {
      $r->setAttribute('customer_name', $r->contract_id && $r->contract ? $this->context($r->contract)['customer_name'] : null);
    });
    if ($this->kind() === 'discount') {
      $ids = [];
      foreach ($collection as $row) {
        if ($row->created_by) $ids[] = (int) $row->created_by;
        foreach ((array) $row->approved_by as $approver) {
          $id = is_array($approver) ? ($approver['id'] ?? null) : $approver;
          if (is_numeric($id)) $ids[] = (int) $id;
        }
      }
      $names = User::query()->whereIn('id', array_unique($ids))->pluck('name', 'id');
      foreach ($collection as $row) {
        $row->setAttribute('creator_name', $names[$row->created_by] ?? ($row->created_by ? '#' . $row->created_by : '—'));
        $approvers = [];
        foreach ((array) $row->approved_by as $approver) {
          $uid = is_array($approver) ? ($approver['id'] ?? null) : $approver;
          $stored = is_array($approver) ? ($approver['name'] ?? null) : null;
          $approvers[] = $stored ?: ($names[$uid] ?? ($uid ? '#' . $uid : null));
        }
        $row->setAttribute('approver_names', array_values(array_filter($approvers)));
      }
    }
    return response()->json($rows);
  }
  public function show($id)
  {
    if (!ctype_digit((string)$id)) abort(404);
    $r = ($this->model())::findOrFail($id);
    $r->setAttribute('customer_name', $r->contract_id ? $this->context($r->contract)['customer_name'] : null);
    return response()->json(['data' => $r]);
  }
  public function contracts(Request $request)
  {
    $d = $request->validate(['search' => 'nullable|string|max:120']);
    $s = trim($d['search'] ?? '');
    $q = RentalContract::with(['booking.customer', 'booking.customerCompany', 'booking.company', 'booking.car'])->orderByDesc('id');
    if ($s !== '') $q->where(function ($x) use ($s) {
      $x->where('id', 'like', "%$s%")->orWhereHas('booking.customer', fn($c) => $c->where('name', 'like', "%$s%"))->orWhereHas('booking.customerCompany', fn($c) => $c->where('name', 'like', "%$s%"));
    });
    return response()->json(['data' => $q->limit(70)->get()->map(fn($c) => array_merge(['id' => $c->id], $this->context($c)))]);
  }
  protected function data(Request $r): array
  {
    return $r->validate([
      'contract_id' => 'required|integer|exists:contracts,id',
      'transportation_id' => 'nullable|integer',
      'date' => 'required|date_format:Y-m-d',
      'amount' => 'required|numeric|min:0|max:9999999999',
      'note' => 'nullable|string|max:4000',
    ]);
  }
  protected function save(Request $request, ?int $id = null)
  {
    $values = $this->data($request);
    $contract = RentalContract::findOrFail($values['contract_id']);
    $values['company_name'] = $this->context($contract)['company_name'];
    return DB::transaction(function () use ($id, $values, $request) {
      $model = $this->model();
      $row = $id ? $model::query()->lockForUpdate()->findOrFail($id) : new $model;
      if ($this->kind() === 'discount') {
        if ($id && $row->is_approved) throw ValidationException::withMessages(['amount' => ['Approved discounts cannot be edited.']]);
        $values['created_by'] = $row->created_by ?: auth()->id();
        if (!$id) {
          $values['is_approved'] = false;
          $values['approved_by'] = [];
        }
      }
      if ($this->kind() === 'gasoline') {
        $values['car_id'] = (int)$request->validate(['car_id' => 'required|integer|exists:vehicles,id'])['car_id'];
        $contract = RentalContract::with('booking')->findOrFail($values['contract_id']);
        if ((int)$contract->booking?->car_id !== $values['car_id']) throw ValidationException::withMessages(['car_id' => ['The selected car does not belong to the contract.']]);
      }
      $row->fill($values);
      $row->save();
      return response()->json(['data' => $row], $id ? 200 : 201);
    });
  }
  public function store(Request $r)
  {
    return $this->save($r);
  }
  public function update(Request $r, $id)
  {
    if (!ctype_digit((string)$id)) abort(404);
    return $this->save($r, $id);
  }
  public function destroy($id)
  {
    if (!ctype_digit((string)$id)) abort(404);
    return DB::transaction(function () use ($id) {
      $model = $this->model();
      $r = $model::lockForUpdate()->findOrFail($id);
      if ($this->kind() === 'discount' && $r->is_approved) throw ValidationException::withMessages(['id' => ['Approved discounts cannot be deleted.']]);
      $r->delete();
      return response()->json(['message' => 'Deleted']);
    });
  }
}
