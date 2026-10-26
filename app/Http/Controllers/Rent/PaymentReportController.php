<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{Company, Payment};
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentReportController extends Controller
{
    public function pdf(Request $request): Response
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['box', 'daily'])],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['required_if:mode,box', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:mode,box', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $now = Carbon::now(config('app.timezone', 'Asia/Amman'));
        $from = $data['mode'] === 'daily' ? $now->toDateString() : $data['from'];
        $to = $data['mode'] === 'daily' ? $now->toDateString() : $data['to'];
        $company = Company::with('logo')->findOrFail($data['company_id']);

        // Resolve the internal fleet company from the source, not the receiving bank.
        // LEFT JOINs preserve historical payments while source filters exclude unrelated records.
        $rows = Payment::query()
            ->from('payments as p')
            ->leftJoin('contracts as ct', 'ct.id', '=', 'p.contract_id')
            ->leftJoin('bookings as cb', 'cb.id', '=', 'ct.booking_id')
            ->leftJoin('bookings as b', 'b.id', '=', 'p.booking_id')
            ->leftJoin('sold_cars as sc', 'sc.id', '=', 'p.sold_car_id')
            ->leftJoin('customers as cu', 'cu.id', '=', 'p.customer_id')
            ->leftJoin('customer_companies as cc', 'cc.id', '=', 'p.customer_company_id')
            ->whereBetween('p.date', [$from, $to])
            ->where(function ($q) use ($company) {
                $q->where(function ($x) use ($company) {
                    $x->where('p.payment_for', 'contract')
                        ->whereNotNull('p.contract_id')
                        ->where('cb.company_id', $company->id);
                })->orWhere(function ($x) use ($company) {
                    $x->where('p.payment_for', 'booking')
                        ->whereNotNull('p.booking_id')
                        ->where('b.company_id', $company->id);
                })->orWhere(function ($x) use ($company) {
                    $x->where('p.payment_for', 'sold car')
                        ->whereNotNull('p.sold_car_id')
                        ->where('sc.company_id', $company->id);
                });
            })
            ->when(!empty($data['user_id']), fn($q) => $q->where('p.user_id', $data['user_id']))
            ->orderBy('p.date')
            ->orderBy('p.id')
            ->get([
                'p.id',
                'p.contract_id',
                'p.booking_id',
                'p.sold_car_id',
                'p.customer_id',
                'p.customer_company_id',
                'p.sold_car_customer_name',
                'p.user_id',
                'p.user_name',
                'p.payment_for',
                'p.date',
                'p.payment_type',
                'p.payment_value',
                'cu.name as individual_name',
                'cc.name as customer_company_name',
            ]);

        // Previous sold-car payments can contain the name even when the current row does not.
        $soldIds = $rows->pluck('sold_car_id')->filter()->unique()->values()->all();
        $soldNames = empty($soldIds) ? collect() : DB::table('payments')
            ->whereIn('sold_car_id', $soldIds)
            ->whereNotNull('sold_car_customer_name')
            ->where('sold_car_customer_name', '<>', '')
            ->orderBy('id')
            ->get(['sold_car_id', 'sold_car_customer_name'])
            ->unique('sold_car_id')
            ->pluck('sold_car_customer_name', 'sold_car_id');

        $rows->each(function ($row) use ($soldNames) {
            $row->reference = $row->contract_id ? (string)$row->contract_id
                : ($row->booking_id ? 'B-' . $row->booking_id
                    : ($row->sold_car_id ? 'S-' . $row->sold_car_id : '—'));
            $row->customer_name = $row->individual_name
                ?: ($row->customer_company_name
                    ?: ($row->sold_car_customer_name ?: ($soldNames->get($row->sold_car_id) ?: '—')));
        });

        $employee = !empty($data['user_id'])
            ? (DB::table('users')->where('id', $data['user_id'])->value('name') ?: ('User #' . $data['user_id']))
            : 'All';

        $logoData = null;
        $path = $company->logo?->path;
        if ($path && Storage::disk('public')->exists($path)) {
            $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';
            if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $logoData = 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($path));
            }
        }

        $pdf = Pdf::loadView('rent.payment-report', [
            'company' => $company,
            'rows' => $rows,
            'logoData' => $logoData,
            'from' => $from,
            'to' => $to,
            'employee' => $employee,
            'mode' => $data['mode'],
            'total' => $rows->sum('payment_value'),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('payments-' . $company->id . '-' . $from . '-' . $to . '.pdf');
    }
}
