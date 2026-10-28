<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Available Cars</title>
    <style>
        @page {
            margin: 22px 26px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #182b40;
            font-size: 11px;
        }

        .report-header {
            text-align: center;
            margin-bottom: 14px;
        }

        .logo {
            display: block;
            margin: 0 auto 7px;
            max-width: 110px;
            max-height: 68px;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin: 3px 0 7px;
        }

        h1 {
            font-size: 20px;
            margin: 6px 0 12px;
        }

        .meta {
            border: 1px solid #d0d8e1;
            padding: 10px;
            margin-bottom: 18px;
        }

        .meta table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta td {
            padding: 4px 6px;
        }

        .prices {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .prices th {
            background: #6e93b8;
            color: #fff;
            padding: 11px 6px;
            font-size: 10px;
            text-align: center;
        }

        .prices td {
            border-bottom: 1px solid #d6dde6;
            padding: 11px 6px;
            font-size: 10px;
            text-align: center;
            word-wrap: break-word;
        }

        .prices tbody tr:nth-child(even) {
            background: #f7f9fc;
        }

        .car-photo {
            display: block;
            width: 86px;
            height: 55px;
            object-fit: contain;
            margin: 0 auto;
        }

        .photo-empty {
            color: #98a3b1;
            font-size: 9px;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #7b8799;
        }
    </style>
</head>

<body>
    <div class="report-header">
        @if($logoSrc)
        <img class="logo" src="{{ $logoSrc }}" alt="Company logo">
        @endif
        @if($mainCompanyName)
        <div class="company-name">{{ $mainCompanyName }}</div>
        @endif
        <h1>Available Cars</h1>
    </div>
    <div class="meta">
        <table>
            <tr>
                <td>Client Type: {{ ucfirst($clientType) }}</td>
                <td>Customer Company: {{ $companyName ?? '—' }}</td>
            </tr>
            <tr>
                <td>From: {{ $start->format('Y-m-d H:i') }}</td>
                <td>To: {{ $end->format('Y-m-d H:i') }}</td>
                <td>Currency: {{ $currency }}</td>
            </tr>
        </table>
    </div>
    <table class="prices">
        <thead>
            <tr>
                <th style="width:16%">Car Image</th>
                <th style="width:15%">Brand</th>
                <th style="width:15%">Model</th>
                <th style="width:12%">Model Year</th>
                <th style="width:12%">Rent Days</th>
                <th style="width:15%">Daily Price</th>
                <th style="width:16%">Total Price</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                <td>
                    @if(!empty($row['image_src']))
                    <img class="car-photo" src="{{ $row['image_src'] }}" alt="Car image">
                    @else
                    <span class="photo-empty">No image</span>
                    @endif
                </td>
                <td>{{ $row['brand'] }}</td>
                <td>{{ $row['model'] }}</td>
                <td>{{ $row['model_year'] }}</td>
                <td>{{ $row['rent_days'] }}</td>
                <td>{{ number_format($row['daily_price'], 2) }} {{ $currency }}</td>
                <td>{{ number_format($row['total_price'], 2) }} {{ $currency }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="empty">No available car rental prices for the selected period.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>