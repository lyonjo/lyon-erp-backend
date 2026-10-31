@php
$part = $part ?? 'name';
$detailFont = max(8, min(40, (int) ($detailFont ?? 13)));
$nameFont = max(8, min(80, (int) ($nameFont ?? 13)));
$companyName = $companyName ?? null;
$logoSrc = $logoSrc ?? null;
@endphp

@if ($part === 'header')
{{-- Fixed-width two-column header. The image has an explicit height so
         mPDF cannot expand the company's full-resolution logo to page size. --}}
<table style="width:100%;border-collapse:collapse;table-layout:fixed;">
    <tr>
        <td style="width:58%;text-align:left;vertical-align:middle;padding:0;font-family:dejavusans;font-size:18pt;font-weight:bold;color:#192d47;">
            {{ $companyName ?: '' }}
        </td>
        <td style="width:42%;text-align:right;vertical-align:middle;padding:0;">
            @if (!empty($logoSrc))
            <img src="{{ $logoSrc }}" style="height:22mm;" height="83" alt="">
            @endif
        </td>
    </tr>
</table>
@elseif ($part === 'name')
{{-- x/y positioning is handled in the controller, not by page-flow CSS. --}}
<div style="width:100%;text-align:center;font-family:dejavusans;font-size:{{ $nameFont }}pt;line-height:1.2;color:#333333;">
    {{ $customerName ?? '-' }}
</div>
@elseif ($part === 'footer')
<table style="width:100%;border-collapse:collapse;table-layout:fixed;font-family:dejavusans;font-size:{{ $detailFont }}pt;color:#444444;">
    <tr>
        <td style="width:33.333%;vertical-align:top;padding:0 3mm 0 0;">
            <div><strong>Pickup Time:</strong> {{ $pickupTime ?? '-' }}</div>
            <div><strong>Car No.:</strong> {{ $carNo ?? '-' }}</div>
            <div><strong>Vehicle:</strong> {{ $vehicle ?? '-' }}</div>
        </td>
        <td style="width:33.333%;vertical-align:top;padding:0 3mm;">
            <div><strong>From / To:</strong> {{ $from ?? '-' }} - {{ $to ?? '-' }}</div>
            <div><strong>Driver:</strong> {{ $driver ?? '-' }}</div>
            <div><strong>Flight:</strong> {{ $flight ?? '-' }}</div>
        </td>
        <td style="width:33.333%;vertical-align:top;padding:0 0 0 3mm;">
            <div><strong>Date:</strong> {{ $date ?? '-' }}</div>
        </td>
    </tr>
</table>
@endif