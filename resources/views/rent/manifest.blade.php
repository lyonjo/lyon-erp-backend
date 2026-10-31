@php
// The report deliberately uses table-based normal document flow: mPDF does not
// position absolutely-placed HTML/CSS reliably with zero-margin A4 landscape.
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
$nameFont = max(14, min(80, (int) ($nameFont ?? 44)));
$detailFont = max(8, min(40, (int) ($detailFont ?? 13)));
// Keep the name centered visually, like the original manifest sample.
// Avoid moving detail rows when a long name is entered.
$nameTop = 27;
if (mb_strlen($customerName) > 28) $nameTop = 29;
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
            font-family: dejavusans;
            color: #484848;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
        }

        .manifest {
            width: 297mm;
        }

        .headrow {
            height: 31mm;
        }

        .brand-table {
            width: 100%;
        }

        .brand-table td {
            vertical-align: middle;
        }

        .brand-name {
            width: 50%;
            text-align: left;
            padding-left: 16mm;
            font-size: 18pt;
            font-weight: bold;
            color: #182c48;
        }

        .brand-logo {
            width: 50%;
            text-align: right;
            padding-right: 16mm;
        }

        .brand-logo img {
            width: 40mm;
        }

        .name-row {
            height: 137mm;
        }

        .name-area {
            vertical-align: top;
            text-align: center;

            padding-top: {
                    {
                    $nameTop
                }
            }

            mm;
            padding-left: 11mm;
            padding-right: 11mm;
        }

        .person-name {
            font-size: {
                    {
                    $detailFont
                }
            }

            pt;
            font-weight: normal;
            color: #111;
            line-height: 1.3;
            text-align: center;
        }

        .gap-row {
            height: 9mm;
        }

        .details-row {
            height: 26mm;
        }

        .details-wrap {
            padding-left: 7mm;
            padding-right: 7mm;
            vertical-align: top;
        }

        .details {
            width: 100%;
        }

        .details td {
            width: 33.333%;

            font-size: {
                    {
                    $detailFont
                }
            }

            pt;
            line-height: 1.45;
            vertical-align: top;
            padding: 0 8mm;
        }

        .detail {
            margin: 0 0 1.6mm 0;
        }

        .label {
            font-weight: bold;
        }

        .end-row {
            height: 7mm;
        }
    </style>
</head>

<body>
    <table class="manifest">
        <tr class="headrow">
            <td>
                <table class="brand-table">
                    <tr>
                        <td class="brand-name">{{ $companyName ?: '' }}</td>
                        <td class="brand-logo">@if($logoSrc)<img src="{{ $logoSrc }}" width="150" alt="">@endif</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr class="name-row">
            <td class="name-area">
                <div class="person-name">{{ $customerName }}</div>
            </td>
        </tr>
        <tr class="gap-row">
            <td></td>
        </tr>
        <tr class="details-row">
            <td class="details-wrap">
                <table class="details">
                    <tr>
                        <td>
                            <div class="detail"><span class="label">Pickup Time:</span> &nbsp;{{ $pickupTime }}</div>
                            <div class="detail"><span class="label">Car No.:</span> &nbsp;{{ $carNo }}</div>
                            <div class="detail"><span class="label">Vehicle:</span> &nbsp;{{ $vehicle }}</div>
                        </td>
                        <td>
                            <div class="detail"><span class="label">From / To:</span> &nbsp;{{ $from }} - {{ $to }}</div>
                            <div class="detail"><span class="label">Driver:</span> &nbsp;{{ $driver }}</div>
                            <div class="detail"><span class="label">Flight:</span> &nbsp;{{ $flight }}</div>
                        </td>
                        <td>
                            <div class="detail"><span class="label">Date:</span> &nbsp;{{ $date }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr class="end-row">
            <td></td>
        </tr>
    </table>
</body>

</html>