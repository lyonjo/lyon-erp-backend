<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <style>
        /* mPDF uses physical points: the earlier px-based layout rendered much too small on A4. */
        body {
            font-family: dejavusans, sans-serif;
            font-size: 9.5pt;
            color: #333;
            margin: 0;
            padding: 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td {
            vertical-align: top;
        }

        .header {
            text-align: center;
            margin-bottom: 13pt;
        }

        .logo {
            height: 65px;
            max-width: 210px;
        }

        .contract-title {
            font-size: 14pt;
            font-weight: bold;
            margin: 8pt 0 3pt;
        }

        .contract-sub {
            font-size: 10pt;
        }

        .section {
            border: 1px solid #d9dee6;
            margin-bottom: 10pt;
            page-break-inside: avoid;
        }

        .section-heading {
            background-color: #3d85c6;
            color: #fff;
            font-size: 9.2pt;
            font-weight: bold;
            padding: 6pt 8pt;
        }

        .section-content {
            background-color: #fff;
            padding: 7pt 8pt;
        }

        .info td {
            padding: 3.7pt 4pt;
            font-size: 8.4pt;
            line-height: 1.35;
            border: 0;
        }

        .layout {
            table-layout: fixed;
            width: 100%;
            margin-bottom: 10pt;
        }

        .layout>tbody>tr>td {
            padding: 0;
            vertical-align: top;
        }

        .layout .gutter {
            width: 10px;
        }

        .panel {
            width: 100%;
            border: 1px solid #d9dee6;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .panel .panel-head {
            background-color: #3d85c6;
            color: #fff;
            padding: 6pt 7pt;
            font-weight: bold;
            font-size: 9pt;
            text-align: left;
        }

        .panel .panel-body {
            background-color: #fff;
            padding: 6pt 7pt;
        }

        .terms-paragraph {
            background: #fff;
            border: 0;
            text-decoration: none;
            font-weight: normal;
            font-size: 8.8pt;
            line-height: 1.32;
            margin: 0 0 7pt;
            padding: 0;
        }

        .terms-paragraph * {
            background: transparent;
            border: 0;
            text-decoration: none;
        }

        .money {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.15pt;
        }

        .money td {
            padding: 3.6pt 2pt;
            border: 0;
            vertical-align: top;
            line-height: 1.32;
        }

        .money .money-label {
            width: 60%;
        }

        .money .money-num {
            width: 40%;
            text-align: right;
        }

        .money .sum td {
            border-top: 1px solid #d9dee6;
            font-weight: bold;
            padding-top: 5pt;
        }

        .signature {
            text-align: left;
            font-weight: bold;
            font-size: 9pt;
            margin-top: 20pt;
            padding-top: 8pt;
            padding-left: 4pt;
            border: 0;
            background: transparent;
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
        <div style="font-size:10pt;font-weight:bold">{{ $company->name }}</div>
        <div class="contract-title">Rental Car Contract / عقد تأجير سيارة</div>
        <div class="contract-sub">{{ strtoupper((string)$contract->status) }} &nbsp; | &nbsp; Contract #{{ $contract->id }}</div>
    </div>
    <table class="section">
        <tr>
            <td class="section-heading">CUSTOMER DETAILS</td>
        </tr>
        <tr>
            <td class="section-content">
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
            </td>
        </tr>
    </table>
    <table class="section">
        <tr>
            <td class="section-heading">CAR DETAILS</td>
        </tr>
        <tr>
            <td class="section-content">
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
            </td>
        </tr>
    </table>
    <table class="layout">
        <tr>
            <td style="width:33%">
                <table class="panel">
                    <tr>
                        <td class="panel-head">CUSTOMER LICENSE</td>
                    </tr>
                    <tr>
                        <td class="panel-body">
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
                        </td>
                    </tr>
                </table>
            </td>
            <td class="gutter"></td>
            <td style="width:65%">
                <table class="panel">
                    <tr>
                        <td class="panel-head">ADDITIONAL DRIVER</td>
                    </tr>
                    <tr>
                        <td class="panel-body">
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
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <table class="layout">
        <tr>
            <td style="width:67%">
                <table class="panel">
                    <tr>
                        <td class="panel-head">TERMS &amp; CONDITIONS / الشروط والأحكام</td>
                    </tr>
                    <tr>
                        <td class="panel-body">
                            @if($contractTerms)
                            <div class="terms-paragraph" dir="ltr">{{ $contractTerms->paragraph_en }}</div>
                            <div class="terms-paragraph" dir="rtl" style="text-align:right">{{ $contractTerms->paragraph_ar }}</div>
                            @else <div class="terms-paragraph">No terms have been configured.</div> @endif
                        </td>
                    </tr>
                </table>
                <div class="signature">Renter Name and Signature ...................................</div>
            </td>
            <td class="gutter"></td>
            <td style="width:31%">
                <table class="panel">
                    <tr>
                        <td class="panel-head">SUMMARY</td>
                    </tr>
                    <tr>
                        <td class="panel-body">
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
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>