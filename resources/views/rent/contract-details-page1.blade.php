<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 10px;
            color: #26364a
        }

        .center {
            text-align: center
        }

        .logo {
            height: 65px;
            max-width: 240px
        }

        .heading {
            font-weight: bold;
            font-size: 19px;
            margin: 9px 0
        }

        .card {
            border: 1px solid #d9e0e8;
            margin: 10px 0;
            page-break-inside: avoid
        }

        .bar {
            background: #3d85c6;
            color: #fff;
            padding: 8px 10px;
            font-weight: bold;
            font-size: 11px
        }

        .info,
        .charges {
            width: 100%;
            border-collapse: collapse
        }

        .info td {
            padding: 6px 8px;
            width: 50%;
            border-bottom: 1px solid #e7ebf0;
            vertical-align: top
        }

        .charges th {
            background: #3d85c6;
            color: #fff;
            text-align: left;
            padding: 7px
        }

        .charges td {
            border: 1px solid #d9e0e8;
            padding: 7px
        }

        .right {
            text-align: right
        }

        .total {
            background: #f0f4f8;
            font-weight: bold
        }

        .terms {
            padding: 9px;
            white-space: pre-line;
            line-height: 1.6
        }

        .sign {
            margin-top: 18px;
            text-align: center;
            font-weight: bold
        }
    </style>
</head>

<body>
    @php
    $c=$booking->customer;$cc=$booking->customerCompany;$d=$c?->details;$v=$booking->car;$ad=$contract->additionalDrivers->first();
    $show=static fn($value)=>filled($value)?$value:'—';
    $date=static fn($value)=>$value instanceof \DateTimeInterface?$value->format('Y-m-d'):$value;
    @endphp
    <div class="center">@if($logoSrc)<img class="logo" src="{{ $logoSrc }}" alt="Logo"><br>@endif
        <strong style="font-size:13px">{{ $company->name }}</strong>
        <div class="heading">Rental Car Contract / عقد تأجير سيارة</div>
        <strong>{{ strtoupper((string)$contract->status) }} &nbsp; | &nbsp; Contract #{{ $contract->id }}</strong>
    </div>
    <div class="card">
        <div class="bar">CUSTOMER DETAILS / بيانات العميل</div>
        <table class="info">
            <tr>
                <td><b>Name:</b> {{ $show($c?->name ?? $cc?->name) }}</td>
                <td><b>Booking:</b> {{ $booking->id }}</td>
            </tr>
            <tr>
                <td><b>Email:</b> {{ $show($c?->email ?? $cc?->email) }}</td>
                <td><b>Phone:</b> {{ $show($c?->full_phone ?? $cc?->phone_number) }}</td>
            </tr>
            <tr>
                <td><b>Address:</b> {{ $show($c?->address ?? $cc?->address) }}</td>
                <td><b>Nationality:</b> {{ $show($d?->nationality) }}</td>
            </tr>
            <tr>
                <td><b>Date of Birth:</b> {{ $show($date($d?->date_of_birth)) }}</td>
                <td><b>Place of Birth:</b> {{ $show($d?->place_of_birth) }}</td>
            </tr>
            <tr>
                <td><b>ID/Passport Issued:</b> {{ $show($date($d?->passport_date_of_issue)) }}</td>
                <td><b>ID/Passport Expires:</b> {{ $show($date($d?->passport_date_of_expire)) }}</td>
            </tr>
            <tr>
                <td colspan="2"><b>ID/Passport Place of Issue:</b> {{ $show($d?->passport_place_of_issue) }}</td>
            </tr>
        </table>
    </div>
    <div class="card">
        <div class="bar">CAR DETAILS / بيانات السيارة</div>
        <table class="info">
            <tr>
                <td><b>Vehicle No.:</b> {{ $show($v?->number) }}</td>
                <td><b>Vehicle:</b> {{ $show(trim(($v?->brand?->name??'').' '.($v?->model?->name??'').' '.($v?->model_year??''))) }}</td>
            </tr>
            <tr>
                <td><b>Pick-up:</b> {{ $booking->start_date }} {{ $booking->start_time }}</td>
                <td><b>Drop-off:</b> {{ $booking->end_date }} {{ $booking->end_time }}</td>
            </tr>
            <tr>
                <td><b>Pick-up Location:</b> {{ $show($booking->pick_up_location) }}</td>
                <td><b>Return Location:</b> {{ $show($booking->return_location) }}</td>
            </tr>
            <tr>
                <td><b>Rental Days:</b> {{ $days }}</td>
                <td><b>KM Out / KM In:</b> {{ $show($contract->km_out) }} / {{ $show($contract->km_in) }}</td>
            </tr>
        </table>
    </div>
    <div class="card">
        <div class="bar">LICENSE & ADDITIONAL DRIVER / رخصة القيادة والسائق الإضافي</div>
        <table class="info">
            <tr>
                <td><b>License Number:</b> {{ $show($d?->license_no) }}</td>
                <td><b>Additional Driver:</b> {{ $show($ad?->name) }}</td>
            </tr>
            <tr>
                <td><b>License Issued:</b> {{ $show($date($d?->license_date_of_issue)) }}</td>
                <td><b>Additional License:</b> {{ $show($ad?->license_number) }}</td>
            </tr>
            <tr>
                <td><b>License Expiry:</b> {{ $show($date($d?->license_date_of_expire)) }}</td>
                <td><b>Additional Expiry:</b> {{ $show($date($ad?->expiry_date)) }}</td>
            </tr>
            <tr>
                <td><b>License Issue Place:</b> {{ $show($d?->license_place_of_issue) }}</td>
                <td><b>Additional Issue Place:</b> {{ $show($ad?->place_of_issue) }}</td>
            </tr>
        </table>
    </div>
    <div class="card">
        <div class="bar">FINANCIAL SUMMARY / الملخص المالي</div>
        <table class="charges">
            <tr>
                <th width="7%">#</th>
                <th>Charge</th>
                <th class="right" width="26%">Amount ({{ $currency }})</th>
            </tr>
            @foreach($rows as $row)<tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $row['label'] }}</td>
                <td class="right">{{ number_format($row['amount'],2) }} {{ $currency }}</td>
            </tr>@endforeach
            <tr class="total">
                <td colspan="2">Subtotal</td>
                <td class="right">{{ number_format($subtotal,2) }} {{ $currency }}</td>
            </tr>
            <tr>
                <td colspan="2">Tax</td>
                <td class="right">{{ number_format($tax,2) }} {{ $currency }}</td>
            </tr>
            <tr class="total">
                <td colspan="2">TOTAL AMOUNT OWED / إجمالي المستحق</td>
                <td class="right">{{ number_format($totalOwed,2) }} {{ $currency }}</td>
            </tr>
        </table>
    </div>
    <div class="card">
        <div class="bar">TERMS & CONDITIONS / الشروط والأحكام</div>
        @if($contractTerms)
        <div class="terms" dir="ltr">{{ $contractTerms->paragraph_en }}</div>
        <div class="terms" dir="rtl" style="text-align:right">{{ $contractTerms->paragraph_ar }}</div>
        @else<div class="terms">No terms have been configured.</div>@endif
    </div>
    <div class="sign">Renter Signature / توقيع المستأجر: ......................................</div>
</body>

</html>