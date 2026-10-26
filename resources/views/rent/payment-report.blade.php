<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Payments Report</title>
    <style>
        @page {
            margin: 20px 32px 25px
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #3b414b;
            font-size: 10px;
            margin: 0
        }

        .head {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 16px
        }

        .head td {
            width: 50%;
            border: 0;
            vertical-align: middle
        }

        .logo {
            height: 60px;
            max-width: 185px
        }

        .company {
            text-align: right;
            font-size: 13px;
            font-weight: bold
        }

        .title {
            text-align: center;
            font-size: 17px;
            font-weight: 600;
            margin: 10px 0 12px
        }

        .meta {
            border: 2px solid #d3d9e0;
            background: #f7f9fb;
            border-radius: 8px;
            padding: 10px 12px;
            margin: 0 0 13px
        }

        .meta table {
            width: 100%;
            border-collapse: collapse
        }

        .meta td {
            width: 33.333%;
            text-align: center
        }

        .headbar {
            background: #779ebb;
            border-radius: 8px 8px 0 0;
            color: white;
            padding: 9px 4px;
            margin-bottom: 5px
        }

        .rowbox {
            border: 2px solid #d0d6de;
            border-radius: 8px;
            background: white;
            padding: 9px 4px;
            margin: 5px 0;
            page-break-inside: avoid
        }

        .lines {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed
        }

        .lines td {
            text-align: center;
            vertical-align: middle;
            padding: 0 3px;
            overflow-wrap: break-word;
            word-wrap: break-word
        }

        .headbar td {
            color: white;
            font-weight: 600;
            font-size: 9px
        }

        .rowbox td {
            font-size: 9px;
            color: #30333a
        }

        .wid {
            width: 9%
        }

        .wref {
            width: 11%
        }

        .wcustomer {
            width: 21%
        }

        .wuser {
            width: 14%
        }

        .wservice {
            width: 11%
        }

        .wdate {
            width: 12%
        }

        .wmethod {
            width: 12%
        }

        .wamount {
            width: 10%
        }

        .total {
            text-align: right;
            margin-top: 14px;
            color: #475569;
            font-weight: bold;
            font-size: 11px
        }

        .empty {
            text-align: center;
            padding: 20px;
            border: 2px solid #d0d6de;
            border-radius: 8px
        }
    </style>
</head>

<body>
    <table class="head">
        <tr>
            <td>@if($logoData)<img class="logo" src="{{ $logoData }}" alt="Company logo">@endif</td>
            <td class="company">{{ $company->name }}</td>
        </tr>
    </table>
    <div class="title">{{ $mode === 'daily' ? 'Daily Payments Report' : 'Payments Report' }} ({{ $rows->count() }})</div>
    <div class="meta">
        <table>
            <tr>
                <td>From date: {{ $from }}</td>
                <td>To Date: {{ $to }}</td>
                <td>User Name: {{ $employee }}</td>
            </tr>
        </table>
    </div>
    <div class="headbar">
        <table class="lines">
            <tr>
                <td class="wid">Payment ID</td>
                <td class="wref">Contract ID</td>
                <td class="wcustomer">Customer Name</td>
                <td class="wuser">User Name</td>
                <td class="wservice">Service</td>
                <td class="wdate">Date</td>
                <td class="wmethod">Type</td>
                <td class="wamount">Value</td>
            </tr>
        </table>
    </div>
    @forelse($rows as $payment)
    <div class="rowbox">
        <table class="lines">
            <tr>
                <td class="wid">{{ $payment->id }}</td>
                <td class="wref">{{ $payment->reference }}</td>
                <td class="wcustomer">{{ $payment->customer_name }}</td>
                <td class="wuser">{{ $payment->user_name ?: ('User #' . $payment->user_id) }}</td>
                <td class="wservice">{{ $payment->payment_for === 'sold car' ? 'Sold Car' : ucfirst($payment->payment_for) }}</td>
                <td class="wdate">{{ substr((string)$payment->date,0,10) }}</td>
                <td class="wmethod">{{ $payment->payment_type }}</td>
                <td class="wamount">{{ number_format((float)$payment->payment_value,2) }}</td>
            </tr>
        </table>
    </div>
    @empty<div class="empty">No payments found for the selected filters.</div>@endforelse
    <div class="total">Total: {{ number_format((float)$total, 2) }} JOD</div>
</body>

</html>