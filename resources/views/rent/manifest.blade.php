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
            color: #414141;
        }

        .page-border {
            position: absolute;
            left: 3mm;
            top: 3mm;
            width: 291mm;
            height: 204mm;
            border: .7pt solid #333;
        }

        .header {
            position: absolute;
            top: 11mm;
            left: 14mm;
            width: 269mm;
        }

        .header-table {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }

        .header-table td {
            width: 50%;
            vertical-align: middle;
        }

        .company-name {
            font-weight: bold;
            font-size: 18pt;
            text-align: left;
            color: #152c49;
        }

        .company-logo {
            text-align: right;
        }

        .company-logo img {
            max-height: 22mm;
            max-width: 55mm;
        }

        .customer-name-box {
            position: absolute;

            top: {
                    {
                    $namePaddingTop
                }
            }

            mm;
            left: 9mm;
            width: 279mm;
            height: 92mm;
            text-align: center;
        }

        .customer-name {
            padding-top: 36mm;
            text-align: center;

            font-size: {
                    {
                    $nameFont
                }
            }

            pt;
            line-height: 1.25;
            font-weight: 600;
            color: #111;
        }

        .details-footer {
            position: absolute;
            bottom: 12mm;
            left: 8mm;
            width: 281mm;
        }

        .details {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .details td {
            width: 33.333%;
            vertical-align: top;
            padding: 0 7mm;

            font-size: {
                    {
                    $detailFont
                }
            }

            pt;
            line-height: 1.6;
        }

        .detail-row {
            margin: 0 0 5mm 0;
        }

        .label {
            font-weight: bold;
        }

        .value {
            font-weight: 500;
        }
    </style>
</head>

<body>
    <div class="page-border"></div>
    @if($companyName || $logoSrc)
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="company-name">{{ $companyName ?? '' }}</td>
                <td class="company-logo">@if($logoSrc)<img src="{{ $logoSrc }}" alt="">@endif</td>
            </tr>
        </table>
    </div>
    @endif
    <div class="customer-name-box">
        <div class="customer-name">{{ $customerName }}</div>
    </div>
    <div class="details-footer">
        <table class="details">
            <tr>
                <td>
                    <p class="detail-row"><span class="label">Pickup Time:</span> <span class="value">{{ $pickupTime }}</span></p>
                    <p class="detail-row"><span class="label">Car No.:</span> <span class="value">{{ $carNo }}</span></p>
                    <p class="detail-row"><span class="label">Vehicle:</span> <span class="value">{{ $vehicle }}</span></p>
                </td>
                <td>
                    <p class="detail-row"><span class="label">From / To:</span> <span class="value">{{ $from }} - {{ $to }}</span></p>
                    <p class="detail-row"><span class="label">Driver:</span> <span class="value">{{ $driver }}</span></p>
                    <p class="detail-row"><span class="label">Flight:</span> <span class="value">{{ $flight }}</span></p>
                </td>
                <td>
                    <p class="detail-row"><span class="label">Date:</span> <span class="value">{{ $date }}</span></p>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>