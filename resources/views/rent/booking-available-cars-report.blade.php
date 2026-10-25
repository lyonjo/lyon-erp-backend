<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Available Cars</title>
    <style>
        @page {
            margin: 22px 25px
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #182b40;
            font-size: 11px
        }

        h1 {
            text-align: center;
            font-size: 22px;
            margin: 12px 0 15px
        }

        .meta {
            border: 2px solid #d0d8e1;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 18px
        }

        .meta table {
            width: 100%
        }

        .meta td {
            padding: 3px
        }

        .section {
            page-break-inside: avoid;
            margin: 15px 0 24px
        }

        h2 {
            text-align: center;
            font-size: 14px;
            margin: 14px 0 12px
        }

        .head {
            background: #6e93b8;
            color: white;
            border-radius: 9px 9px 0 0;
            padding: 10px 9px
        }

        .head table,
        .item table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse
        }

        .head td {
            text-align: center;
            font-weight: bold;
            color: white;
            font-size: 10px
        }

        .item {
            border: 2px solid #bdbdbd;
            border-radius: 0 0 9px 9px;
            padding: 10px 9px;
            margin-top: 6px
        }

        .item td {
            text-align: center;
            font-size: 10px;
            word-wrap: break-word
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #7b8799
        }
    </style>
</head>

<body>
    <h1>Available Cars ({{ $total }})</h1>
    <div class="meta">
        <table>
            <tr>
                <td>Client Type: {{ ucfirst($clientType) }}</td>
                <td>Customer Company: {{ $companyName ?? '—' }}</td>
                <td>Rental Days: {{ $days }}</td>
            </tr>
            <tr>
                <td>From: {{ $start->format('Y-m-d H:i') }}</td>
                <td>To: {{ $end->format('Y-m-d H:i') }}</td>
                <td>Currency: {{ $currency }}</td>
            </tr>
        </table>
    </div>
    @forelse($groups as $branch => $cars)
    <section class="section">
        <h2>{{ $branch }} ({{ $cars->count() }})</h2>
        <div class="head">
            <table>
                <tr>
                    <td width="16%">Vehicle Number</td>
                    <td width="16%">Vehicle Brand</td>
                    <td width="17%">Vehicle Name</td>
                    <td width="11%">Model Year</td>
                    <td width="25%">Company Name</td>
                    <td width="15%">Daily Price</td>
                </tr>
            </table>
        </div>
        @foreach($cars as $car)
        <div class="item">
            <table>
                <tr>
                    <td width="16%">{{ $car['number'] }}</td>
                    <td width="16%">{{ $car['brand'] }}</td>
                    <td width="17%">{{ $car['model'] }}</td>
                    <td width="11%">{{ $car['year'] }}</td>
                    <td width="25%">{{ $car['owner'] }}</td>
                    <td width="15%">{{ number_format($car['day_price'],2) }} {{ $currency }}</td>
                </tr>
            </table>
        </div>
        @endforeach
    </section>
    @empty<div class="empty">No available cars for the selected period.</div>@endforelse
</body>

</html>