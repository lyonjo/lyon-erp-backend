<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $contract->id }}</title>
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 10pt;
            color: #293746;
            margin: 0
        }

        .brand {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 10px
        }

        .brand td {
            vertical-align: middle;
            width: 50%
        }

        .brand .name {
            text-align: right;
            font-weight: bold;
            font-size: 13pt
        }

        .logo {
            max-height: 53px;
            max-width: 170px
        }

        .title {
            text-align: center;
            font-size: 17pt;
            font-weight: bold;
            margin: 12px 0
        }

        .pill {
            background: #3d85c6;
            color: #fff;
            text-align: center;
            font-weight: bold;
            border-radius: 12px;
            padding: 7px 12px;
            margin: 12px 0 18px
        }

        .info {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 12px
        }

        .info td {
            padding: 5px 7px;
            vertical-align: top
        }

        .info strong {
            color: #182c42
        }

        .intro {
            line-height: 1.65;
            margin: 12px 0 17px
        }

        .head {
            width: 100%;
            background: #3d85c6;
            color: #fff;
            border-collapse: collapse;
            margin-bottom: 8px
        }

        .head th {
            padding: 9px 10px;
            text-align: left
        }

        .head .num {
            width: 9%;
            text-align: center
        }

        .head .amt {
            width: 28%;
            text-align: right
        }

        .line {
            width: 100%;
            border: 2px solid #95afc4;
            border-radius: 9px;
            margin-bottom: 7px;
            border-collapse: separate
        }

        .line td {
            padding: 8px 10px;
            vertical-align: middle
        }

        .line .num {
            width: 9%;
            text-align: center
        }

        .line .amt {
            width: 28%;
            text-align: right;
            white-space: nowrap
        }

        .summary {
            width: 100%;
            border: 2px solid #3f596c;
            background: #f7f9fb;
            border-radius: 9px;
            margin-bottom: 7px;
            border-collapse: separate
        }

        .summary td {
            padding: 8px 10px
        }

        .summary .amt {
            width: 28%;
            text-align: right;
            white-space: nowrap
        }

        .summary .num {
            width: 9%
        }

        .grand {
            background: #e9eef2;
            font-weight: bold;
            font-size: 12pt
        }

        .footer {
            margin-top: 24px;
            border-top: 1px solid #d9e0e5;
            padding-top: 9px;
            font-size: 8pt;
            color: #52616f
        }

        .ar {
            direction: rtl;
            unicode-bidi: embed
        }
    </style>
</head>

<body>
    <table class="brand">
        <tr>
            <td>@if($logoSrc)<img class="logo" src="{{ $logoSrc }}" alt="Company Logo">@endif</td>
            <td class="name"><span class="ar">{{ $company->name }}</span></td>
        </tr>
    </table>
    <div class="title">Invoice / فاتورة</div>
    <div class="pill">Invoice Number / رقم الفاتورة: {{ $contract->id }}</div>
    <table class="info">
        <tr>
            <td><strong>Date:</strong> {{ now()->format('Y-m-d') }}</td>
            <td><strong>Booking No.:</strong> {{ $booking->id }}</td>
        </tr>
        <tr>
            <td><strong>Customer:</strong> <span class="ar">{{ $booking->customer->name ?? $booking->customerCompany->name ?? '—' }}</span></td>
            <td><strong>Vehicle:</strong> {{ $booking->car->number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>From:</strong> {{ $booking->start_date }} {{ $booking->start_time }}</td>
            <td><strong>To:</strong> {{ $booking->end_date }} {{ $booking->end_time }}</td>
        </tr>
        <tr>
            <td><strong>Rental Days:</strong> {{ $days }}</td>
            <td><strong>Currency:</strong> {{ $currency }}</td>
        </tr>
    </table>
    <div class="intro">Dear Customer, please find below the charges for renting vehicle {{ $booking->car->number ?? '—' }} for {{ $days }} day(s), under contract #{{ $contract->id }}.</div>
    <table class="head">
        <tr>
            <th class="num">S.N.</th>
            <th>Details</th>
            <th class="amt">Amount ({{ $currency }})</th>
        </tr>
    </table>
    @foreach($rows as $row)
    <table class="line">
        <tr>
            <td class="num">{{ $loop->iteration }}.</td>
            <td>{{ $row['label'] }}</td>
            <td class="amt">{{ number_format($row['amount'], 2) }} {{ $currency }}</td>
        </tr>
    </table>
    @endforeach
    @if(count($rows) === 0)<table class="line">
        <tr>
            <td colspan="3">No charges.</td>
        </tr>
    </table>@endif
    <table class="summary">
        <tr>
            <td class="num"></td>
            <td><strong>Sub Total</strong></td>
            <td class="amt">{{ number_format($subtotal, 2) }} {{ $currency }}</td>
        </tr>
    </table>
    <table class="summary">
        <tr>
            <td class="num"></td>
            <td><strong>Tax</strong></td>
            <td class="amt">{{ number_format($tax, 2) }} {{ $currency }}</td>
        </tr>
    </table>
    <table class="summary">
        <tr>
            <td class="num"></td>
            <td><strong>Total Paid</strong></td>
            <td class="amt">{{ number_format($paid, 2) }} {{ $currency }}</td>
        </tr>
    </table>
    <table class="summary grand">
        <tr>
            <td class="num"></td>
            <td>Total Due / الإجمالي المستحق</td>
            <td class="amt">{{ number_format($balance, 2) }} {{ $currency }}</td>
        </tr>
    </table>
    <div class="footer">@if(!empty($company->phone))Phone: {{ $company->phone }} &nbsp; @endif @if(!empty($company->email))Email: {{ $company->email }} &nbsp; @endif @if(!empty($company->website))Website: {{ $company->website }}@endif</div>
</body>

</html>