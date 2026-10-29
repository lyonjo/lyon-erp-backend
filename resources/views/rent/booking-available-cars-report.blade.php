<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Available Cars</title>
    <style>
        @page {
            margin: 12mm 10mm 13mm;
        }

        body {
            font-family: dejavusans, sans-serif;
            font-size: 10pt;
            color: #182b40;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
        }

        .header-table td {
            width: 50%;
            vertical-align: middle;
            border: none;
        }

        .header-company {
            text-align: left;
            font-size: 17pt;
            font-weight: bold;
        }

        .header-logo {
            text-align: right;
        }

        .logo {
            max-height: 23mm;
            max-width: 40mm;
        }

        h1 {
            text-align: center;
            font-size: 17pt;
            margin: 1mm 0 4mm;
        }

        .meta {
            border: 1px solid #d0d8e1;
            padding: 3mm;
            margin-bottom: 5mm;
        }

        .meta table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta td {
            padding: 2mm;
            text-align: left;
        }

        .prices {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .prices th {
            background: #6e93b8;
            color: #fff;
            padding: 3mm 1.5mm;
            font-size: 9pt;
            text-align: center;
        }

        .prices td {
            border-bottom: 1px solid #d6dde6;
            padding: 3mm 1.5mm;
            font-size: 9pt;
            text-align: center;
            vertical-align: middle;
            overflow-wrap: break-word;
        }

        .prices tbody tr:nth-child(even) {
            background: #f7f9fc;
        }

        .prices tr {
            page-break-inside: avoid;
        }

        .car-photo {
            display: block;
            width: 35mm;
            max-height: 25mm;
            margin: 0 auto;
        }

        .photo-empty {
            color: #98a3b1;
            font-size: 9pt;
        }

        .empty {
            text-align: center;
            padding: 10mm;
            color: #7b8799;
        }
    </style>
</head>

<body>
    <table class="header-table">
        <tr>
            <td class="header-company">{{ $mainCompanyName ?? '' }}</td>
            <td class="header-logo">@if($logoSrc)<img class="logo" src="{{ $logoSrc }}" alt="Company logo">@endif</td>
        </tr>
    </table>
    <h1>Available Cars</h1>
    <div class="meta">
        <table>
            <tr>
                <td>Client Type: {{ ucfirst($clientType) }}</td>
                <td>Currency: {{ $currency }}</td>
            </tr>
            <tr>
                <td>From: {{ $start->format('Y-m-d H:i') }}</td>
                <td>To: {{ $end->format('Y-m-d H:i') }}</td>
            </tr>
        </table>
    </div>
    <table class="prices">
        <thead>
            <tr>
                <th style="width:22%">Car Image</th>
                <th style="width:13%">Brand</th>
                <th style="width:15%">Model</th>
                <th style="width:12%">Model Year</th>
                <th style="width:14%">Daily Price</th>
                <th style="width:10%">Rent Days</th>
                <th style="width:14%">Total Price</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                <td>@if(!empty($row['image_src']))<img class="car-photo" src="{{ $row['image_src'] }}" alt="Car image">@else<span class="photo-empty">No image</span>@endif</td>
                <td>{{ $row['brand'] }}</td>
                <td>{{ $row['model'] }}</td>
                <td>{{ $row['model_year'] }}</td>
                <td>{{ number_format($row['daily_price'], 2) }} {{ $currency }}</td>
                <td>{{ $row['rent_days'] }}</td>
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