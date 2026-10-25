<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 32px 32px 38px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #24324a;
            font-size: 11px
        }

        .heading {
            width: 100%;
            margin-bottom: 20px
        }

        .heading td {
            border: 0;
            vertical-align: middle
        }

        .logo {
            max-height: 58px;
            max-width: 160px
        }

        h1 {
            text-align: center;
            font-size: 18px;
            margin: 15px 0 5px
        }

        .meta {
            text-align: center;
            color: #65758d;
            margin-bottom: 18px
        }

        .group {
            font-weight: bold;
            font-size: 13px;
            margin: 16px 0 8px
        }

        table.data {
            width: 100%;
            border-collapse: collapse
        }

        .data th {
            background: #6d9ab8;
            color: white;
            padding: 9px 6px;
            text-align: left
        }

        .data td {
            padding: 8px 6px;
            border-bottom: 1px solid #d7e0e8
        }

        .total {
            text-align: right;
            margin-top: 13px;
            color: #52657a
        }

        .small {
            color: #8490a0
        }
    </style>
</head>

<body>
    <table class="heading">
        <tr>
            <td style="width:35%">@if($logoData)<img class="logo" src="{{ $logoData }}" alt="Logo">@endif</td>
            <td style="width:65%;text-align:right;font-size:14px;font-weight:bold">{{ $company->name }}</td>
        </tr>
    </table>
    <h1>Available Cars ({{ $vehicles->count() }})</h1>
    <div class="meta">{{ $generatedAt->format('Y-m-d H:i') }} | {{ $branch?->name ?? 'All branches' }}</div>
    <table class="data">
        <thead>
            <tr>
                <th>Vehicle Number</th>
                <th>Vehicle Brand</th>
                <th>Vehicle Model</th>
                <th>Year</th>
                <th>Branch</th>
            </tr>
        </thead>
        <tbody>@forelse($vehicles as $car)<tr>
                <td>{{ $car->number }}</td>
                <td>{{ $car->brand?->name }}</td>
                <td>{{ $car->model?->name }}</td>
                <td>{{ $car->model_year }}</td>
                <td>{{ $car->branch?->name ?? $car->current_location }}</td>
            </tr>
            @empty<tr>
                <td colspan="5" style="text-align:center">No cars available at the time of this report.</td>
            </tr>@endforelse</tbody>
    </table>
    <div class="total">Total available vehicles: {{ $vehicles->count() }}</div>
</body>

</html>