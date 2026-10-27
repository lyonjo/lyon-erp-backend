<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 5mm 8mm;
        }

        body {
            font-family: dejavusans, sans-serif;
            font-size: 9px;
            color: #333;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            vertical-align: top;
        }

        .header {
            text-align: center;
            margin-bottom: 8px;
        }

        .logo {
            height: 48px;
            max-width: 210px;
        }

        .contract-title {
            font-size: 15px;
            font-weight: bold;
            margin: 5px 0 1px;
        }

        .contract-sub {
            font-size: 11px;
            line-height: 1.6;
        }

        .card {
            border: 2px solid #d9dee6;
            margin-bottom: 7px;
            page-break-inside: avoid;
        }

        .blue {
            background: #3d85c6;
            color: #fff;
            font-weight: bold;
            padding: 7px 9px;
            font-size: 10px;
            text-transform: uppercase;
        }

        .info {
            width: 100%;
            border-collapse: collapse;
        }

        .info td {
            border: 0;
            padding: 3px 6px;
            font-size: 9px;
            line-height: 1.35;
        }

        .info b {
            font-weight: bold;
        }

        .split {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 7px;
            page-break-inside: avoid;
        }

        .split>tbody>tr>td {
            padding: 0;
            vertical-align: top;
        }

        .split .left {
            padding-right: 5px;
        }

        .split .right {
            padding-left: 5px;
        }

        .split .card {
            margin: 0;
        }

        .split .inner {
            padding: 6px 7px;
        }

        .split .inner .info td {
            padding: 2px 3px;
        }

        .financial .left {
            width: 68%;
        }

        .financial .right {
            width: 32%;
        }

        .financial .blue {
            font-size: 9.5px;
        }

        .terms {
            white-space: pre-line;
            line-height: 1.35;
            font-size: 8.3px;
            padding: 3px 2px 6px;
            overflow-wrap: break-word;
        }

        .money {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.4px;
        }

        .money td {
            border: 0;
            padding: 3px 2px;
            vertical-align: top;
            line-height: 1.3;
        }

        .money .money-label {
            width: 64%;
        }

        .money .money-num {
            text-align: right;
            white-space: nowrap;
            width: 36%;
        }

        .money .sum td {
            font-weight: bold;
            border-top: 1px solid #d9dee6;
            padding-top: 5px;
        }

        .charge-details {
            color: #666;
            font-size: 7.5px;
        }

        .signature {
            font-weight: bold;
            text-align: center;
            padding-top: 7px;
            font-size: 9px;
        }
    </style>
</head>

<body>
    @php
    $c=$booking->customer; $cc=$booking->customerCompany; $d=$c?->details; $v=$booking->car;
    $ad=$contract->additionalDrivers->first();
    $show=static fn($value)=>filled($value)?$value:'N/A';
    $date=static fn($value)=>$value instanceof \DateTimeInterface?$value->format('Y-m-d'):$value;
    @endphp
    <div class="header">
        @if($logoSrc)<img class="logo" src="{{ $logoSrc }}" alt="Company logo">@endif
        <div style="font-size:10px;font-weight:bold">{{ $company->name }}</div>
        <div class="contract-title">Rental Car Contract / عقد تأجير سيارة</div>
        <div class="contract-sub">{{ strtoupper((string)$contract->status) }} &nbsp; | &nbsp; Contract #{{ $contract->id }}</div>
    </div>
    <div class="card">
        <div class="blue">CUSTOMER DETAILS</div>
        <table class="info">
            <tr>
                <td colspan="3"><b>Name:</b> {{ $show($c?->name ?? $cc?->name ?? $cc?->company_name) }}</td>
            </tr>
            <tr>
                <td><b>Address:</b> {{ $show($c?->address ?? $cc?->address) }}</td>
                <td><b>Date of Issue:</b> {{ $show($date($d?->passport_date_of_issue)) }}</td>
                <td><b>ID Number:</b> {{ $show($d?->passport_number ?? null) }}</td>
            </tr>
            <tr>
                <td><b>Expiry Date:</b> {{ $show($date($d?->passport_date_of_expire)) }}</td>
                <td><b>Date of Birth:</b> {{ $show($date($d?->date_of_birth)) }}</td>
                <td><b>Nationality:</b> {{ $show($d?->nationality) }}</td>
            </tr>
            <tr>
                <td><b>Place of Issue:</b> {{ $show($d?->passport_place_of_issue) }}</td>
                <td><b>Place of Birth:</b> {{ $show($d?->place_of_birth) }}</td>
                <td><b>Mobile:</b> {{ $show($c?->full_phone ?? $cc?->phone_number) }}</td>
            </tr>
        </table>
    </div>
    <div class="card">
        <div class="blue">CAR DETAILS</div>
        <table class="info">
            <tr>
                <td><b>Car No.:</b> {{ $show($v?->number) }}</td>
                <td><b>Car Model:</b> {{ $show($v?->model_year) }}</td>
                <td><b>Pick-up Date:</b> {{ $booking->start_date }}</td>
                <td><b>Drop-off Date:</b> {{ $booking->end_date }}</td>
            </tr>
            <tr>
                <td><b>Car Brand:</b> {{ $show($v?->brand?->name) }}</td>
                <td><b>Km In:</b> {{ $show($contract->km_in) }}</td>
                <td><b>Pick-up Time:</b> {{ $booking->start_time }}</td>
                <td><b>Drop-off Time:</b> {{ $booking->end_time }}</td>
            </tr>
            <tr>
                <td><b>Car Type:</b> {{ $show($v?->model?->name) }}</td>
                <td><b>Km Out:</b> {{ $show($contract->km_out) }}</td>
                <td><b>Rental Period:</b> {{ $days }}</td>
                <td><b>Booking:</b> {{ $booking->id }}</td>
            </tr>
        </table>
    </div>
    <table class="split">
        <tr>
            <td class="left" style="width:34%">
                <div class="card">
                    <div class="blue">CUSTOMER LICENSE</div>
                    <div class="inner">
                        <table class="info">
                            <tr>
                                <td><b>Expiry Date:</b> {{ $show($date($d?->license_date_of_expire)) }}</td>
                            </tr>
                            <tr>
                                <td><b>Date of Issue:</b> {{ $show($date($d?->license_date_of_issue)) }}</td>
                            </tr>
                            <tr>
                                <td><b>Place of Issue:</b> {{ $show($d?->license_place_of_issue) }}</td>
                            </tr>
                            <tr>
                                <td><b>Driver's License No.:</b> {{ $show($d?->license_no) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
            <td class="right" style="width:66%">
                <div class="card">
                    <div class="blue">ADDITIONAL DRIVER</div>
                    <div class="inner">
                        <table class="info">
                            <tr>
                                <td><b>Additional Driver:</b> {{ $show($ad?->name) }}</td>
                            </tr>
                            <tr>
                                <td><b>Expiry Date:</b> {{ $show($date($ad?->expiry_date)) }}</td>
                            </tr>
                            <tr>
                                <td><b>Date of Issue:</b> {{ $show($date($ad?->date_of_issue)) }}</td>
                            </tr>
                            <tr>
                                <td><b>Place of Issue:</b> {{ $show($ad?->place_of_issue) }}</td>
                            </tr>
                            <tr>
                                <td><b>License Number:</b> {{ $show($ad?->license_number) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>
    <table class="split financial">
        <tr>
            <td class="left">
                <div class="card">
                    <div class="blue">TERMS &amp; CONDITIONS / الشروط والأحكام</div>
                    <div class="inner">
                        @if($contractTerms)
                        <div class="terms" dir="ltr">{{ $contractTerms->paragraph_en }}</div>
                        <div class="terms" dir="rtl" style="text-align:right">{{ $contractTerms->paragraph_ar }}</div>
                        @else <div class="terms">No terms have been configured.</div> @endif
                        <div class="signature">Renter Name and Signature ...................................</div>
                    </div>
                </div>
            </td>
            <td class="right">
                <div class="card">
                    <div class="blue">SUMMARY</div>
                    <div class="inner">
                        <table class="money">
                            <tr>
                                <td class="money-label">Car Price / Day:</td>
                                <td class="money-num">{{ number_format((float)$booking->day_company_price,2) }} {{ $currency }}</td>
                            </tr>
                            <tr>
                                <td class="money-label">Rental Days:</td>
                                <td class="money-num">{{ $days }}</td>
                            </tr>
                            @foreach($rows as $row)
                            <tr>
                                <td class="money-label">{{ $row['label'] }}</td>
                                <td class="money-num">{{ number_format($row['amount'],2) }} {{ $currency }}</td>
                            </tr>
                            @endforeach
                            <tr class="sum">
                                <td>Subtotal:</td>
                                <td class="money-num">{{ number_format($subtotal,2) }} {{ $currency }}</td>
                            </tr>
                            <tr>
                                <td>Tax:</td>
                                <td class="money-num">{{ number_format($tax,2) }} {{ $currency }}</td>
                            </tr>
                            <tr class="sum">
                                <td>Total Amount Owed:</td>
                                <td class="money-num">{{ number_format($totalOwed,2) }} {{ $currency }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>

</html>