<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\RentalContract;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class RentalContractInvoiceController extends Controller
{
    public function pdf(RentalContract $contract): Response
    {
        $contract->load([
            'booking.customer',
            'booking.customerCompany',
            'booking.company.logo',
            'booking.car.brand',
            'booking.car.model',
            'booking.extraItems',
        ]);
        $booking = $contract->booking;
        abort_unless($booking && $booking->service === 'rent', 404);
        $company = $booking->company;
        abort_unless($company, 404, 'The booking company was not found.');

        // Currency is tenant-specific, managed in Rent > Settings.
        // Tenancy middleware has already selected the current tenant database.
        $configuredCurrency = Schema::hasTable('settings')
            ? DB::table('settings')->where('key', 'currency')->value('value')
            : null;
        $currency = strtoupper(trim((string) ($configuredCurrency ?? '')));
        if ($currency === '') {
            $currency = 'JOD'; // Default only when no tenant currency has been configured.
        }
        $money = static fn($n): float => round((float) ($n ?? 0), 2);
        $rows = [];
        $add = static function (string $label, float $amount) use (&$rows): void {
            if (abs($amount) >= 0.005) {
                $rows[] = ['label' => $label, 'amount' => round($amount, 2)];
            }
        };

        $days = max(1, (int) $booking->total_days);
        $dailyPrice = $money($booking->day_company_price);
        $add('Rental Charge (' . $days . ' days × ' . number_format($dailyPrice, 2) . ' ' . $currency . ')', $days * $dailyPrice);

        // Use the booked pivot snapshot rather than today's Extra Item catalog prices.
        foreach ($booking->extraItems as $item) {
            $pivot = $item->pivot;
            $line = $pivot && $pivot->line_total !== null
                ? $money($pivot->line_total)
                : $money(($pivot->unit_price ?? $item->price) * (!empty($pivot->is_daily) ? $days : 1));
            $add((string) $item->name, $line);
        }

        $add('Extra Kilometers', $money($booking->extra_km_price));
        $add('Branch Fee', $money($booking->branch_fee));

        // Each linked charge appears individually, and the same exact rows build the subtotal.
        if (Schema::hasTable('traffic_violations')) {
            foreach (DB::table('traffic_violations')->where('contract_id', $contract->id)->orderBy('id')->get(['id', 'violation_no', 'violation_value']) as $violation) {
                $label = 'Traffic Violation #' . ($violation->violation_no ?: $violation->id);
                $add($label, $money($violation->violation_value));
            }
        }
        if (Schema::hasTable('extra_money')) {
            foreach (DB::table('extra_money')->where('contract_id', $contract->id)->orderBy('id')->get(['id', 'value']) as $extra) {
                $add('Extra Money #' . $extra->id, $money($extra->value));
            }
        }
        if (Schema::hasTable('refunds')) {
            foreach (DB::table('refunds')->where('contract_id', $contract->id)->orderBy('id')->get(['id', 'amount']) as $refund) {
                $add('Refund #' . $refund->id, $money($refund->amount));
            }
        }
        if (Schema::hasTable('gasoline')) {
            foreach (DB::table('gasoline')->where('contract_id', $contract->id)->orderBy('id')->get(['id', 'amount']) as $gasoline) {
                $add('Gasoline #' . $gasoline->id, $money($gasoline->amount));
            }
        }
        if (Schema::hasTable('discounts')) {
            foreach (DB::table('discounts')->where('contract_id', $contract->id)->where('is_approved', true)->orderBy('id')->get(['id', 'amount']) as $discount) {
                $add('Approved Discount #' . $discount->id, -$money($discount->amount));
            }
        }

        $subtotal = round(array_sum(array_column($rows, 'amount')), 2);
        $tax = $money($booking->tax);
        $totalBeforePayments = round($subtotal + $tax, 2);
        $paid = 0.0;
        if (Schema::hasTable('payments')) {
            // Count direct contract payments plus direct booking payments, without double-counting rows.
            $paid = $money(DB::table('payments')
                ->where(function ($query) use ($contract, $booking) {
                    $query->where('contract_id', $contract->id)
                        ->orWhere(function ($q) use ($booking) {
                            $q->whereNull('contract_id')->where('booking_id', $booking->id);
                        });
                })->sum('company_value'));
        }
        $balance = round($totalBeforePayments - $paid, 2);

        $logoSrc = $this->companyLogo($company);
        $html = view('rent.contract-invoice-pdf', compact(
            'contract',
            'booking',
            'company',
            'currency',
            'rows',
            'subtotal',
            'tax',
            'paid',
            'balance',
            'totalBeforePayments',
            'logoSrc',
            'days'
        ))->render();

        $tempDir = storage_path('app/mpdf');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }
        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 12,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $tempDir,
        ]);
        $pdf->WriteHTML($html);

        return response($pdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contract-invoice-' . $contract->id . '.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function companyLogo(\App\Models\Rent\Company $company): ?string
    {
        // Company logos are polymorphic images, not a `companies.logo` string column.
        // The Image model stores the relative tenant file path in images.path.
        $company->loadMissing('logo');
        $storedPath = $company->logo?->path;
        if (!is_string($storedPath) || trim($storedPath) === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', trim($storedPath)), '/');
        if (str_contains($path, '..') || str_contains($path, '://')) {
            return null;
        }

        // Same tenant disk convention as the existing Available Cars PDF.
        foreach (['public', 'local'] as $diskName) {
            $disk = Storage::disk($diskName);
            if (!$disk->exists($path)) {
                continue;
            }

            $bytes = $disk->get($path);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return 'data:' . $mime . ';base64,' . base64_encode($bytes);
            }
        }

        return null;
    }
}
