<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Available Cars</title>
    <style>
        /* The old project uses a landscape A4 sheet with a roomy report body. */
        @page {
            margin: 20px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            padding: 30px;
            margin: 0;
            color: #333;
            background: #fff;
            font-size: 11pt;
        }

        .logos {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .logos td {
            width: 33.33%;
            vertical-align: middle;
            border: none;
        }

        .logos .left {
            text-align: left;
        }

        .logos .center {
            text-align: center;
        }

        .logos .right {
            text-align: right;
            font-size: 12pt;
            font-weight: 700;
        }

        .logo {
            height: 60px;
            max-width: 200px;
        }

        .title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            margin: 20px 0 0;
        }

        .loc {
            text-align: center;
            font-weight: 700;
            margin: 18px 0 10px;
        }

        .section {
            page-break-inside: avoid;
        }

        .headbar {
            background: #6e93b8;
            color: #fff;
            font-weight: 700;
            font-size: 10pt;
            padding: 8px 10px;
            border-radius: 10px 10px 0 0;
        }

        .headbar table,
        .row-box table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .headbar td {
            width: 25%;
            color: #fff;
            text-align: center;
            padding: 0 4px;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
        }

        .row-box {
            margin-top: 6px;
            border: 3px solid #bdbdbd;
            border-radius: 0 0 10px 10px;
            background: #fff;
            padding: 8px 10px;
            page-break-inside: avoid;
        }

        .row-box td {
            width: 25%;
            font-size: 12px;
            text-align: center;
            color: #111;
            padding: 0 4px;
            vertical-align: top;
            overflow-wrap: break-word;
        }

        .empty-box {
            margin-top: 6px;
            border: 3px solid #bdbdbd;
            border-radius: 0 0 10px 10px;
            background: #fff;
            padding: 10px;
            text-align: center;
            font-weight: 700;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <table class="logos">
        <tr>
            <td class="left">
                @if(!empty($logoData))
                <img src="{{ $logoData }}" class="logo" alt="Company logo">
                @endif
            </td>
            <td class="center">&nbsp;</td>
            <td class="right">{{ $company->name }}</td>
        </tr>
    </table>

    <div class="title">Available Cars ({{ $vehicles->count() }})</div>

    @php
    // Keep the existing query and availability rules. Only arrange its already-filtered records for display.
    $grouped = $vehicles->groupBy(function ($vehicle) {
    return $vehicle->branch?->name ?: ($vehicle->current_location ?: 'Unknown');
    });
    @endphp

    @forelse($grouped as $location => $cars)
    <div class="section">
        <div class="loc">{{ $location }} ({{ $cars->count() }})</div>
        <div class="headbar">
            <table>
                <tr>
                    <td>Vehicle Number</td>
                    <td>Vehicle Name</td>
                    <td>Vehicle Model</td>
                    <td>Company Name</td>
                </tr>
            </table>
        </div>
        @forelse($cars as $car)
        <div class="row-box">
            <table>
                <tr>
                    <td>{{ $car->number }}</td>
                    <td>{{ $car->model?->name ?? '—' }}</td>
                    <td>{{ $car->model_year }}</td>
                    <td>{{ $car->company?->name ?? $company->name }}</td>
                </tr>
            </table>
        </div>
        @empty
        <div class="empty-box">No available cars</div>
        @endforelse
    </div>
    @empty
    <div class="loc">No data found.</div>
    @endforelse
</body>

</html>