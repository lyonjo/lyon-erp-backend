<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{Bank, Booking, Company, CustomerCompany, Payment, RentalContract, SoldCar};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PaymentManagementController extends Controller
{
    private const TYPES = ['contract', 'booking', 'company', 'sold car'];
    private const BANK_FLAGS = ['contract' => 'for_contracts', 'booking' => 'for_booking', 'company' => 'for_companies', 'sold car' => 'for_sold_car'];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $search = trim((string)($data['search'] ?? ''));
        $q = Payment::query()->with(['customer:id,name', 'customerCompany:id,name', 'bank:id,name']);
        if ($search !== '') {
            $q->where(function ($query) use ($search) {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhere('contract_id', 'like', "%{$search}%")
                    ->orWhere('booking_id', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('customerCompany', fn($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }
        $results = $q->orderByDesc('id')->paginate((int)($data['per_page'] ?? 15));
        // Payment customer names on sold cars are not linked to Customer/CustomerCompany.
        $results->getCollection()->each(function ($payment) {
            if ($payment->payment_for === 'sold car') {
                $payment->setAttribute('customer_name', $payment->sold_car_customer_name ?: null);
            }
        });
        return response()->json($results);
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json(['data' => $payment->load(['customer', 'customerCompany', 'bank'])]);
    }


    // Provides the filter lists for Payment Box and Daily Report (report generation is a later phase).
    public function reportOptions(): JsonResponse
    {
        $companies = Company::query()->orderBy('name')->get(['id', 'name']);
        $employees = Payment::query()->whereNotNull('user_id')
            ->select('user_id', 'user_name')->distinct()->orderBy('user_name')->get()
            ->map(fn($p) => ['id' => $p->user_id, 'name' => $p->user_name ?: ('User #' . $p->user_id)]);
        return response()->json(['companies' => $companies, 'employees' => $employees]);
    }

    public function banks(Request $request): JsonResponse
    {
        $data = $request->validate(['payment_for' => ['required', 'in:contract,booking,company,sold car'], 'company_id' => ['nullable', 'integer', 'min:1']]);
        $flag = self::BANK_FLAGS[$data['payment_for']];
        $q = Bank::where($flag, true)->orderBy('name');
        // Bank belongs to an internal fleet company, NOT customer_companies.
        if (!empty($data['company_id'])) $q->where('company_id', (int)$data['company_id']);
        return response()->json(['data' => $q->get(['id', 'name', 'company_id'])]);
    }

    public function customerCompanies(Request $request): JsonResponse
    {
        $search = trim((string)$request->query('search', ''));
        $q = CustomerCompany::query()->select('id', 'name');
        if ($search !== '') $q->where('name', 'like', '%' . $search . '%');
        return response()->json(['data' => $q->orderBy('name')->limit(100)->get()]);
    }

    private function baseValues(string $type, int $referenceId): array
    {
        if ($type === 'contract') {
            $contract = RentalContract::with(['booking.customer', 'booking.customerCompany'])->findOrFail($referenceId);
            $b = $contract->booking;
            if (!$b) throw ValidationException::withMessages(['reference_id' => ['Contract has no booking.']]);
            $amount = (float)$b->company_total;
            $query = Payment::where('contract_id', $contract->id)->where('payment_for', 'contract');
            return [
                'reference_id' => $contract->id,
                'name' => $b->customer?->name ?? $b->customerCompany?->name ?? '—',
                'total_value' => $amount,
                'paid' => (float)(clone $query)->sum('company_value') + (float)Payment::where('booking_id', $b->id)->where('payment_for', 'booking')->sum('company_value'),
                'customer_id' => $b->customer_id,
                'customer_company_id' => $b->customer_company_id,
                'booking_id' => $b->id,
                'contract_id' => $contract->id,
                'company_id' => $b->company_id
            ];
        }
        if ($type === 'booking') {
            $b = Booking::with(['customer', 'customerCompany'])->findOrFail($referenceId);
            $amount = (float)$b->company_total;
            $query = Payment::where('booking_id', $b->id)->where('payment_for', 'booking');
            return [
                'reference_id' => $b->id,
                'name' => $b->customer?->name ?? $b->customerCompany?->name ?? '—',
                'total_value' => $amount,
                'paid' => (float)(clone $query)->sum('company_value') + (float)Payment::where('booking_id', $b->id)->where('payment_for', 'contract')->sum('company_value'),
                'customer_id' => $b->customer_id,
                'customer_company_id' => $b->customer_company_id,
                'booking_id' => $b->id,
                'contract_id' => null,
                'company_id' => $b->company_id
            ];
        }
        if ($type === 'company') {
            $c = CustomerCompany::findOrFail($referenceId);
            // For a customer company, count only its linked rental contracts to avoid booking + contract double-counting.
            $bookingIds = RentalContract::whereIn('booking_id', Booking::where('customer_company_id', $c->id)->select('id'))->pluck('booking_id');
            $total = (float)Booking::whereIn('id', $bookingIds)->sum('company_total');
            $paid = (float)Payment::where('customer_company_id', $c->id)->where('payment_for', 'company')->sum('company_value');
            // Also count direct contract payments for this customer company against its collective debt.
            $paid += (float)Payment::where('customer_company_id', $c->id)->whereIn('payment_for', ['contract', 'booking'])->sum('company_value');
            return ['reference_id' => $c->id, 'name' => $c->name, 'total_value' => $total, 'paid' => $paid, 'customer_id' => null, 'customer_company_id' => $c->id, 'booking_id' => null, 'contract_id' => null, 'company_id' => null];
        }
        if ($type === 'sold car') {
            $s = SoldCar::findOrFail($referenceId);
            $existingName = Payment::query()
                ->where('sold_car_id', $s->id)
                ->where('payment_for', 'sold car')
                ->whereNotNull('sold_car_customer_name')
                ->where('sold_car_customer_name', '!=', '')
                ->orderBy('id')
                ->value('sold_car_customer_name');
            return [
                'reference_id' => $s->id,
                'name' => $existingName ?? '',
                'name_locked' => $existingName !== null,
                'total_value' => (float)$s->price,
                'paid' => (float)Payment::where('sold_car_id', $s->id)->where('payment_for', 'sold car')->sum('company_value'),
                'customer_id' => null,
                'customer_company_id' => null,
                'booking_id' => null,
                'contract_id' => null,
                'company_id' => $s->company_id
            ];
        }
        throw ValidationException::withMessages(['payment_for' => ['Unsupported payment purpose.']]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $d = $request->validate(['payment_for' => ['required', 'in:contract,booking,company,sold car'], 'reference_id' => ['required', 'integer', 'min:1']]);
        $result = $this->baseValues($d['payment_for'], (int)$d['reference_id']);
        return response()->json(['data' => array_merge($result, ['remaining' => round(max(0, $result['total_value'] - $result['paid']), 2)])]);
    }

    public function store(Request $request): JsonResponse
    {
        $d = $request->validate([
            'payment_for' => ['required', 'in:contract,booking,company,sold car'],
            'reference_id' => ['required', 'integer', 'min:1'],
            'sold_car_customer_name' => ['nullable', 'string', 'max:190'],
            'date' => ['required', 'date_format:Y-m-d'],
            'payment_value' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'payment_type' => ['required', 'in:cash,credit_card,check,bank_transfer,smart_link,online'],
            'bank_id' => ['required', 'integer', 'exists:banks,id'],
            'is_bank_transferee' => ['sometimes', 'boolean'],
            'auth_number' => ['required_if:payment_type,credit_card', 'nullable', 'string', 'max:100'],
            'credit_card_number' => ['required_if:payment_type,credit_card', 'nullable', 'string', 'max:50'],
            'check_bank' => ['required_if:payment_type,check', 'nullable', 'string', 'max:190'],
            'check_no' => ['required_if:payment_type,check', 'nullable', 'string', 'max:100'],
            'check_due_date' => ['required_if:payment_type,check', 'nullable', 'date_format:Y-m-d'],
        ]);
        return DB::transaction(function () use ($d, $request) {
            // Serialize first-name establishment for the same sold car.
            if ($d['payment_for'] === 'sold car') {
                SoldCar::query()->whereKey((int)$d['reference_id'])->lockForUpdate()->firstOrFail();
            }
            $base = $this->baseValues($d['payment_for'], (int)$d['reference_id']);
            $soldCarCustomerName = null;
            if ($d['payment_for'] === 'sold car') {
                $requestedName = trim((string)($d['sold_car_customer_name'] ?? ''));
                if (!empty($base['name_locked'])) {
                    $soldCarCustomerName = $base['name'];
                    if ($requestedName !== '' && $requestedName !== $soldCarCustomerName) {
                        throw ValidationException::withMessages([
                            'sold_car_customer_name' => ['The sold-car customer name is already established and cannot be changed by another payment.'],
                        ]);
                    }
                } else {
                    if ($requestedName === '') {
                        throw ValidationException::withMessages([
                            'sold_car_customer_name' => ['Enter the customer name for the first sold-car payment.'],
                        ]);
                    }
                    $soldCarCustomerName = $requestedName;
                }
            }
            $flag = self::BANK_FLAGS[$d['payment_for']];
            $bank = Bank::query()->whereKey($d['bank_id'])->where($flag, true)->first();
            if (!$bank) throw ValidationException::withMessages(['bank_id' => ['Bank is not enabled for this payment type.']]);
            if ($base['company_id'] && (int)$bank->company_id !== (int)$base['company_id'])
                throw ValidationException::withMessages(['bank_id' => ['Bank does not belong to the source fleet company.']]);
            $value = round((float)$d['payment_value'], 2);
            $remaining = round(max(0, $base['total_value'] - $base['paid']), 2);
            if ($value > $remaining + 0.00001) throw ValidationException::withMessages(['payment_value' => ['Payment exceeds remaining balance.']]);
            $admin = $request->user();
            $row = Payment::create([
                'contract_id' => $base['contract_id'],
                'booking_id' => $base['booking_id'],
                'customer_id' => $base['customer_id'],
                'customer_company_id' => $base['customer_company_id'],
                'sold_car_id' => $d['payment_for'] === 'sold car' ? $base['reference_id'] : null,
                'sold_car_customer_name' => $soldCarCustomerName,
                'lawsuit_id' => null,
                'user_id' => $admin?->id,
                'user_name' => $admin?->name ?? $admin?->email ?? 'Admin',
                'bank_id' => $bank->id,
                'payment_for' => $d['payment_for'],
                'payment_type' => $d['payment_type'],
                'is_bank_transferee' => (bool)($d['is_bank_transferee'] ?? false),
                'date' => $d['date'],
                'payment_value' => $value,
                'company_value' => $value, // Same currency; no conversion.
                'auth_number' => $d['payment_type'] === 'credit_card' ? $d['auth_number'] : null,
                'credit_card_number' => $d['payment_type'] === 'credit_card' ? $d['credit_card_number'] : null,
                'check_bank' => $d['payment_type'] === 'check' ? $d['check_bank'] : null,
                'check_no' => $d['payment_type'] === 'check' ? $d['check_no'] : null,
                'check_due_date' => $d['payment_type'] === 'check' ? $d['check_due_date'] : null,
            ]);
            return response()->json(['data' => $row, 'remaining' => round($remaining - $value, 2)], 201);
        });
    }
}
