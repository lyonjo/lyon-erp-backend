<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 26px 26px 32px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #252525;
            font-size: 11px;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }

        .header td {
            vertical-align: top;
            border: 0;
        }

        .logo {
            max-height: 62px;
            max-width: 170px;
        }

        .company-name {
            text-align: right;
            font-size: 13px;
            font-weight: bold;
            padding-top: 15px;
        }

        h1 {
            font-size: 19px;
            text-align: center;
            margin: 16px 0 15px;
        }

        .branch-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin: 12px 0 16px;
        }

        .cars-table {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }

        .cars-table th {
            padding: 8px 7px;
            text-align: center;
            color: white;
            background: #6f96ba;
            font-size: 10px;
            font-weight: normal;
        }

        .cars-table th:first-child {
            border-top-left-radius: 7px;
        }

        .cars-table th:last-child {
            border-top-right-radius: 7px;
        }

        .cars-table td {
            padding: 3px 2px;
            text-align: center;
            border: 0;
        }

        .cell-box {
            border: 2px solid #b7b7b7;
            padding: 6px 5px;
            height: 15px;
            text-align: center;
        }

        .cars-table td:first-child .cell-box {
            border-right: 0;
            border-radius: 6px 0 0 6px;
        }

        .cars-table td:not(:first-child):not(:last-child) .cell-box {
            border-left: 0;
            border-right: 0;
        }

        .cars-table td:last-child .cell-box {
            border-left: 0;
            border-radius: 0 6px 6px 0;
        }

        .empty {
            text-align: center;
            padding: 22px;
            color: #777;
        }
    </style>
</head>

<body>
    <table class="header">
        <tr>
            <td style="width: 45%">@if($logoData)<img class="logo" src="{{ $logoData }}" alt="Company logo">@endif</td>
            <td class="company-name" style="width: 55%">{{ $company->name }}</td>
        </tr>
    </table>
    <h1>Available Cars ({{ $vehicles->count() }})</h1>
    @php
    $groups = $vehicles->groupBy(fn ($car) => $car->branch?->name ?? $car->current_location ?? 'Unassigned');
    @endphp
    @forelse($groups as $branchName => $cars)
    <div class="branch-title">{{ $branchName }} ({{ $cars->count() }})</div>
    <table class="cars-table">
        <thead>
            <tr>
                <th>Vehicle Number</th>
                <th>Vehicle Name</th>
                <th>Vehicle Model</th>
                <th>Company Name</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cars as $car)
            <tr>
                <td>
                    <div class="cell-box">{{ $car->number }}</div>
                </td>
                <td>
                    <div class="cell-box">{{ $car->model?->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="cell-box">{{ $car->model_year }}</div>
                </td>
                <td>
                    <div class="cell-box">{{ $company->name }}</div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @empty
    <div class="empty">No cars available at the time of this report.</div>
    @endforelse
</body>

</html>