@php
$customerName = $customerName ?? '-';
$pickupTime = $pickupTime ?? '-';
$carNo = $carNo ?? '-';
$vehicle = $vehicle ?? '-';
$driver = $driver ?? '-';
$flight = $flight ?? '-';
$date = $date ?? '-';
$from = $from ?? '-';
$to = $to ?? '-';
$companyName = $companyName ?? null;
$logoSrc = $logoSrc ?? null;

$parsePt = function ($value, $default = 13) {
if ($value === null || $value === '') {
return $default;
}

if (is_numeric($value)) {
return (int) $value;
}

if (preg_match('/(\d+)/', (string) $value, $matches)) {
return (int) $matches[1];
}

return $default;
};

// your page currently sends only "font"
$detailFontPt = max(8, min(40, $parsePt($detailFont ?? null, 13)));
$nameFontPt = max(12, min(80, $parsePt($nameFont ?? $detailFont ?? null, $detailFontPt)));

$fmtDate = function ($value) {
if (!$value || $value === '-') {
return '-';
}

try {
return \Carbon\Carbon::parse($value)->format('Y-m-d');
} catch (\Exception $e) {
return (string) $value;
}
};
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #4a4a4a;
        }

        .page {
            width: 297mm;
            height: 210mm;
            padding: 0;
            margin: 0;
            box-sizing: border-box;
        }

        .outer {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .header-row {
            height: 32mm;
        }

        .middle-row {
            height: 128mm;
        }

        .footer-row {
            height: 50mm;
        }

        .header-cell {
            padding: 10mm 14mm 0 14mm;
            vertical-align: top;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .header-table td {
            vertical-align: middle;
        }

        .company-name {
            width: 50%;
            text-align: left;
            font-size: 18pt;
            font-weight: bold;
            color: #182c48;
        }

        .company-logo {
            width: 50%;
            text-align: right;
        }

        .company-logo img {
            max-height: 38mm;
            max-width: 52mm;
            width: auto;
            height: auto;
        }

        .middle-cell {
            vertical-align: middle;
            text-align: center;
            padding: 0 18mm;
        }

        .customer-name {
            margin: 0;
            padding: 0;
            text-align: center;

            font-size: {
                    {
                    $nameFontPt
                }
            }

            pt;
            font-weight: normal;
            color: #4a4a4a;
            line-height: 1.2;
            word-break: break-word;
        }

        .footer-cell {
            vertical-align: bottom;
            padding: 0 10mm 10mm 10mm;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .details-table td {
            width: 33.333%;
            vertical-align: top;

            font-size: {
                    {
                    $detailFontPt
                }
            }

            pt;
            line-height: 1.45;
            color: #4a4a4a;
            padding: 0 8mm;
        }

        .detail-row {
            margin: 0 0 2mm 0;
            white-space: nowrap;
        }

        .label {
            font-weight: bold;
        }

        .value {
            font-weight: normal;
        }
    </style>
</head>

<body>
    <div class="page">
        <table class="outer">
            <tr class="header-row">
                <td class="header-cell">
                    <table class="header-table">
                        <tr>
                            <td class="company-name">
                                {{ $companyName ?: '' }}
                            </td>
                            <td class="company-logo">
                                @if (!empty($logoSrc))
                                <img src="{{ $logoSrc }}" alt="Company Logo">
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="middle-row">
                <td class="middle-cell">
                    <div class="customer-name">
                        {{ $customerName }}
                    </div>
                </td>
            </tr>

            <tr class="footer-row">
                <td class="footer-cell">
                    <table class="details-table">
                        <tr>
                            <td>
                                <p class="detail-row">
                                    <span class="label">Pickup Time:</span>
                                    <span class="value"> {{ $pickupTime }}</span>
                                </p>
                                <p class="detail-row">
                                    <span class="label">Car No.:</span>
                                    <span class="value"> {{ $carNo }}</span>
                                </p>
                                <p class="detail-row">
                                    <span class="label">Vehicle:</span>
                                    <span class="value"> {{ $vehicle }}</span>
                                </p>
                            </td>

                            <td>
                                <p class="detail-row">
                                    <span class="label">From / To:</span>
                                    <span class="value"> {{ $from }} - {{ $to }}</span>
                                </p>
                                <p class="detail-row">
                                    <span class="label">Driver:</span>
                                    <span class="value"> {{ $driver }}</span>
                                </p>
                                <p class="detail-row">
                                    <span class="label">Flight:</span>
                                    <span class="value"> {{ $flight }}</span>
                                </p>
                            </td>

                            <td>
                                <p class="detail-row">
                                    <span class="label">Date:</span>
                                    <span class="value"> {{ $fmtDate($date) }}</span>
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>