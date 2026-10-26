<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{Bank, Booking, Company, CustomerCompany, Payment, RentalContract, SoldCar, TrafficViolation, ExtraMoney};
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
                    ->orWhere('sold_car_customer_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('customerCompany', fn($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }
        $results = $q->orderByDesc('id')->paginate((int)($data['per_page'] ?? 15));
        // Payment customer names on sold cars are not linked to Customer/CustomerCompany.
        $results->getCollection()->each(function ($payment) {
            if ($payment->payment_for === 'sold car') {
                $payment->setAttribute('customer_name', $payment->sold_car_customer_name ?: Payment::query()->where('sold_car_id', $payment->sold_car_id)->where('payment_for', 'sold car')->whereNotNull('sold_car_customer_name')->where('sold_car_customer_name', '!=', '')->orderBy('id')->value('sold_car_customer_name'));
            }
        });
        return response()->json($results);
    }

    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['customer', 'customerCompany', 'bank']);
        if ($payment->customer_company_id && in_array($payment->payment_for, ['contract', 'booking'], true)) {
            $summary = $this->companySummary((int)$payment->customer_company_id);
            $row = collect($summary['rows'])->firstWhere('contract_id', (int)$payment->contract_id);
            $payment->setAttribute('editable_remaining', round(max(0, min(
                $summary['remaining'],
                (float)($row['remaining'] ?? 0)
            )), 2));
        }
        if ($payment->payment_for === 'sold car') {
            $payment->setAttribute('customer_name', $payment->sold_car_customer_name ?: Payment::query()->where('sold_car_id', $payment->sold_car_id)->where('payment_for', 'sold car')->whereNotNull('sold_car_customer_name')->where('sold_car_customer_name', '!=', '')->orderBy('id')->value('sold_car_customer_name'));
        }
        return response()->json(['data' => $payment]);
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

    /** Company receivables consist ONLY of contracts belonging to that customer company.
     *  Bookings that have no contract are deliberately excluded.
     */
    private function companyReceivables(int $companyId): array
    {
        $contracts = RentalContract::query()
            ->whereHas('booking', fn($q) => $q->where('customer_company_id', $companyId))
            ->with('booking:id,customer_company_id,company_total')
            ->orderBy('id')
            ->get(['id', 'booking_id']);

        $rows = [];
        foreach ($contracts as $contract) {
            $total = round((float) ($contract->booking?->company_total ?? 0) + (float) TrafficViolation::where('contract_id', $contract->id)->sum('violation_value') + (float) ExtraMoney::where('contract_id', $contract->id)->sum('value'), 2);
            // Every company allocation is a contract payment with contract_id set.
            $paid = round((float) Payment::query()
                ->where('contract_id', $contract->id)
                ->where('payment_for', 'contract')
                ->sum('company_value'), 2);
            $rows[] = [
                'contract_id' => (int) $contract->id,
                'total' => $total,
                'paid' => $paid,
                'remaining' => round(max(0, $total - $paid), 2),
            ];
        }

        // Preserve the effect of historical unallocated company payments.
        $legacyBalance = round((float) Payment::query()
            ->where('customer_company_id', $companyId)
            ->where('payment_for', 'company')
            ->sum('company_value'), 2);
        foreach ($rows as &$row) {
            if ($legacyBalance <= 0) break;
            $offset = min($legacyBalance, $row['remaining']);
            $row['remaining'] = round($row['remaining'] - $offset, 2);
            $legacyBalance = round($legacyBalance - $offset, 2);
        }
        unset($row);
        return $rows;
    }

    private function companySummary(int $companyId): array
    {
        $rows = $this->companyReceivables($companyId);
        $total = round(array_sum(array_column($rows, 'total')), 2);
        // Calculate paid amount from the contract balances, without counting
        // standalone bookings or payments allocated to bookings without contracts.
        $remaining = round(array_sum(array_column($rows, 'remaining')), 2);
        return [
            'total' => $total,
            'paid' => round(max(0, $total - $remaining), 2),
            'remaining' => $remaining,
            'rows' => $rows,
        ];
    }

    private function rejectCompanySource(Booking $booking): void
    {
        if ($booking->customer_company_id !== null) {
            throw ValidationException::withMessages(['reference_id' => [
                'Customer-company bookings and contracts must be paid through the Companies tab.',
            ]]);
        }
    }

    private function baseValues(string $type, int $referenceId): array
    {
        if ($type === 'contract') {
            $contract = RentalContract::with(['booking.customer', 'booking.customerCompany'])->findOrFail($referenceId);
            $b = $contract->booking;
            if (!$b) throw ValidationException::withMessages(['reference_id' => ['Contract has no booking.']]);
            $this->rejectCompanySource($b);
            $amount = (float)$b->company_total + (float)TrafficViolation::where('contract_id', $contract->id)->sum('violation_value') + (float)ExtraMoney::where('contract_id', $contract->id)->sum('value');
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
            $this->rejectCompanySource($b);
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
            $summary = $this->companySummary((int)$c->id);
            return [
                'reference_id' => $c->id,
                'name' => $c->name,
                'total_value' => $summary['total'],
                'paid' => $summary['paid'],
                'customer_id' => null,
                'customer_company_id' => $c->id,
                'booking_id' => null,
                'contract_id' => null,
                'company_id' => null
            ];
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
            if ($d['payment_for'] === 'company') {
                CustomerCompany::query()->whereKey((int)$d['reference_id'])->lockForUpdate()->firstOrFail();
            }
            if ($d['payment_for'] === 'contract') {
                RentalContract::query()->whereKey((int)$d['reference_id'])->lockForUpdate()->firstOrFail();
            }
            if ($d['payment_for'] === 'booking') {
                Booking::query()->whereKey((int)$d['reference_id'])->lockForUpdate()->firstOrFail();
            }
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
            if ($d['payment_for'] === 'company') {
                $summary = $this->companySummary((int)$base['reference_id']);
                if ($value > $summary['remaining'] + 0.00001) {
                    throw ValidationException::withMessages(['payment_value' => ['Payment exceeds the total outstanding balance of this customer company.']]);
                }
                $still = $value;
                $created = [];
                foreach ($summary['rows'] as $receivable) {
                    if ($still < 0.005) break;
                    $portion = round(min($still, $receivable['remaining']), 2);
                    if ($portion <= 0) continue;
                    $created[] = Payment::create([
                        'contract_id' => $receivable['contract_id'],
                        'booking_id' => null,
                        'customer_id' => null,
                        'customer_company_id' => $base['reference_id'],
                        'sold_car_id' => null,
                        'sold_car_customer_name' => null,
                        'lawsuit_id' => null,
                        'user_id' => $admin?->id,
                        'user_name' => $admin?->name ?? $admin?->email ?? 'Admin',
                        'bank_id' => $bank->id,
                        'payment_for' => 'contract',
                        'payment_type' => $d['payment_type'],
                        'is_bank_transferee' => (bool)($d['is_bank_transferee'] ?? false),
                        'date' => $d['date'],
                        'payment_value' => $portion,
                        'company_value' => $portion,
                        'auth_number' => $d['payment_type'] === 'credit_card' ? ($d['auth_number'] ?? null) : null,
                        'credit_card_number' => $d['payment_type'] === 'credit_card' ? ($d['credit_card_number'] ?? null) : null,
                        'check_bank' => $d['payment_type'] === 'check' ? ($d['check_bank'] ?? null) : null,
                        'check_no' => $d['payment_type'] === 'check' ? ($d['check_no'] ?? null) : null,
                        'check_due_date' => $d['payment_type'] === 'check' ? ($d['check_due_date'] ?? null) : null,
                    ]);
                    $still = round($still - $portion, 2);
                }
                if ($still > 0.005) {
                    throw ValidationException::withMessages(['payment_value' => ['Unable to allocate the full payment to outstanding company contracts.']]);
                }
                return response()->json([
                    'data' => $created,
                    'allocated_count' => count($created),
                    'remaining' => round($summary['remaining'] - $value, 2)
                ], 201);
            }
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
    /** Immutable source of payment: never accept payment_for/reference_id in edits. */
    private function paymentReference(Payment $payment): int
    {
        return match ($payment->payment_for) {
            'contract' => (int) $payment->contract_id,
            'booking' => (int) $payment->booking_id,
            'company' => (int) $payment->customer_company_id,
            'sold car' => (int) $payment->sold_car_id,
            default => throw ValidationException::withMessages(['payment_for' => ['Unsupported payment purpose.']]),
        };
    }

    public function update(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
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
        return DB::transaction(function () use ($payment, $data) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $type = $payment->payment_for;
            $referenceId = $this->paymentReference($payment);
            // Serialize financial edits on the source, preventing two concurrent edits from overpaying.
            if ($type === 'sold car') SoldCar::query()->whereKey($referenceId)->lockForUpdate()->firstOrFail();
            if ($type === 'company') CustomerCompany::query()->whereKey($referenceId)->lockForUpdate()->firstOrFail();
            if ($type === 'booking') Booking::query()->whereKey($referenceId)->lockForUpdate()->firstOrFail();
            if ($type === 'contract') RentalContract::query()->whereKey($referenceId)->lockForUpdate()->firstOrFail();
            if ($payment->customer_company_id !== null && $type === 'booking') {
                throw ValidationException::withMessages(['payment_for' => [
                    'Legacy company booking payments cannot be edited through this contracts-only workflow.',
                ]]);
            }
            $isCompanyAllocation = $payment->customer_company_id !== null && $type === 'contract';
            if ($isCompanyAllocation) {
                CustomerCompany::query()->whereKey((int)$payment->customer_company_id)->lockForUpdate()->firstOrFail();
                $summary = $this->companySummary((int)$payment->customer_company_id);
                $receivable = collect($summary['rows'])->firstWhere('contract_id', (int)$payment->contract_id);
                $base = [
                    'total_value' => $receivable['total'] ?? 0,
                    'paid' => (float)(($receivable['total'] ?? 0) - ($receivable['remaining'] ?? 0)),
                    'company_id' => null
                ];
                $companyCap = round($summary['remaining'] + (float)$payment->company_value, 2);
            } else {
                $base = $this->baseValues($type, $referenceId);
            }
            $allowedTotal = round(max(0, $base['total_value'] - $base['paid'] + (float) $payment->company_value), 2);
            if ($isCompanyAllocation) $allowedTotal = min($allowedTotal, $companyCap);
            $newValue = round((float) $data['payment_value'], 2);
            if ($newValue > $allowedTotal + 0.00001) {
                throw ValidationException::withMessages(['payment_value' => ['Payment exceeds the remaining balance, including the current payment.']]);
            }
            $flag = self::BANK_FLAGS[$isCompanyAllocation ? 'company' : $type];
            $bank = Bank::query()->whereKey($data['bank_id'])->where($flag, true)->first();
            if (!$bank) throw ValidationException::withMessages(['bank_id' => ['Bank is not enabled for this payment type.']]);
            if ($base['company_id'] && (int) $bank->company_id !== (int) $base['company_id']) {
                throw ValidationException::withMessages(['bank_id' => ['Bank does not belong to the source fleet company.']]);
            }
            $payment->update([
                'date' => $data['date'],
                'payment_value' => $newValue,
                'company_value' => $newValue,
                'payment_type' => $data['payment_type'],
                'bank_id' => $bank->id,
                'is_bank_transferee' => (bool)($data['is_bank_transferee'] ?? false),
                'auth_number' => $data['payment_type'] === 'credit_card' ? ($data['auth_number'] ?? null) : null,
                'credit_card_number' => $data['payment_type'] === 'credit_card' ? ($data['credit_card_number'] ?? null) : null,
                'check_bank' => $data['payment_type'] === 'check' ? ($data['check_bank'] ?? null) : null,
                'check_no' => $data['payment_type'] === 'check' ? ($data['check_no'] ?? null) : null,
                'check_due_date' => $data['payment_type'] === 'check' ? ($data['check_due_date'] ?? null) : null,
            ]);
            return response()->json(['data' => $payment->fresh(['bank']), 'remaining' => round($allowedTotal - $newValue, 2)]);
        });
    }

    public function destroy(Payment $payment): JsonResponse
    {
        DB::transaction(function () use ($payment) {
            $row = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $row->delete();
        });
        return response()->json(['message' => 'Payment deleted successfully.']);
    }
}
