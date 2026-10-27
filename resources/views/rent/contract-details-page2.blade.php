<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 8mm;
        }

        body {
            font-family: dejavusans, sans-serif;
            color: #333;
            font-size: 9px;
            margin: 0;
            padding: 0;
        }

        .title {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 12px;
            border-bottom: 1px solid #333;
        }

        .title td {
            width: 50%;
            font-size: 17px;
            font-weight: bold;
            padding: 6px 0;
        }

        .clauses {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .clauses td {
            width: 50%;
            padding: 3px 9px;
            vertical-align: top;
            line-height: 1.3;
            font-size: 8.7px;
            font-weight: bold;
        }

        .clauses tr {
            page-break-inside: avoid;
        }
    </style>
</head>

<body>
    <table class="title">
        <tr>
            <td>Contract Terms</td>
            <td dir="rtl" style="text-align:right">شروط العقد</td>
        </tr>
    </table>
    @if($contractTerms && $contractTerms->clauses->isNotEmpty())
    <table class="clauses">@foreach($contractTerms->clauses as $clause)
        <tr>
            <td dir="ltr">{{ $loop->iteration }} - {{ $clause->text_en }}</td>
            <td dir="rtl" style="text-align:right">{{ $loop->iteration }} - {{ $clause->text_ar }}</td>
        </tr>
        @endforeach
    </table>
    @else<p>No numbered contract clauses have been configured.</p>@endif
</body>

</html>