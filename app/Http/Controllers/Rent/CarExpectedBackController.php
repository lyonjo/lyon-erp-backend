<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\CarReservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CarExpectedBackController extends Controller
{
    public function report(Request $request): Response
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        // Inclusive date range, independent of the time of the drop-off.
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $validated['from'])->startOfDay();
        $endExclusive = CarbonImmutable::createFromFormat('!Y-m-d', $validated['to'])->addDay()->startOfDay();

        $reservations = CarReservation::query()
            ->with([
                'car:id,number,company_id',
                'car.company:id,name',
                'booking:id,customer_id,customer_company_id,return_location,total,currency,status',
                'booking.customer',
                'booking.customerCompany',
            ])
            ->where('dropoff_end_datetime', '>=', $start->format('Y-m-d H:i:s'))
            ->where('dropoff_end_datetime', '<', $endExclusive->format('Y-m-d H:i:s'))
            ->whereHas('booking', fn($q) => $q->whereNotIn('status', ['rejected']))
            ->orderBy('dropoff_end_datetime')
            ->orderBy('id')
            ->get();

        // A return branch is determined by booking.return_location, not the car's current branch.
        $groups = $reservations->groupBy(function (CarReservation $reservation) {
            $location = trim((string) ($reservation->booking?->return_location ?? ''));
            return $location !== '' ? $location : 'Unspecified Return Location';
        });

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'Install the PDF renderer: composer require barryvdh/laravel-dompdf');
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('rent.car-expected-back-report', [
            'groups' => $groups,
            'count' => $reservations->count(),
            'from' => $validated['from'],
            'to' => $validated['to'],
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('car-expected-back-' . $validated['from'] . '-to-' . $validated['to'] . '.pdf');
    }
}
