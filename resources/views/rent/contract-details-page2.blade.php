<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 10px;
            color: #24374c
        }

        .clauses {
            width: 100%;
            border-collapse: collapse
        }

        .clauses td {
            width: 50%;
            vertical-align: top;
            padding: 7px 10px;
            line-height: 1.6;
            border-bottom: 1px solid #e9edf2
        }

        .head {
            font-size: 17px;
            font-weight: bold;
            border-bottom: 1px solid #25344b;
            padding: 9px 0 12px
        }

        .number {
            font-weight: bold;
            color: #3d85c6
        }
    </style>
</head>

<body>
    <table width="100%" class="head">
        <tr>
            <td>Contract Terms</td>
            <td dir="rtl" style="text-align:right">شروط العقد</td>
        </tr>
    </table>
    @if($contractTerms && $contractTerms->clauses->isNotEmpty())
    <table class="clauses">@foreach($contractTerms->clauses as $clause)
        <tr>
            <td dir="ltr"><span class="number">{{ $loop->iteration }}.</span> {{ $clause->text_en }}</td>
            <td dir="rtl" style="text-align:right"><span class="number">{{ $loop->iteration }}.</span> {{ $clause->text_ar }}</td>
        </tr>
        @endforeach
    </table>
    @else<p>No numbered clauses have been configured.</p>@endif
</body>

</html>