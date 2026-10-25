<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Car Expected Back</title>
  <style>
    @page {
      margin: 18px 20px;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: DejaVu Sans, sans-serif;
      font-size: 10px;
      color: #222;
    }

    .title {
      text-align: center;
      font-size: 19px;
      margin: 0 0 14px;
      font-weight: normal;
    }

    .datebox {
      border: 2px solid #d3d6dc;
      border-radius: 9px;
      background: #f9fafb;
      padding: 8px 12px;
      margin-bottom: 20px;
    }

    .datebox table {
      width: 100%;
      border-collapse: collapse;
    }

    .datebox td {
      width: 50%;
      font-size: 11px;
    }

    .datebox .end {
      text-align: right;
    }

    .loc {
      font-weight: normal;
      text-align: center;
      font-size: 13px;
      margin: 24px 0 9px;
    }

    .headbar {
      background: #6e93b8;
      color: #fff;
      border-radius: 9px 9px 0 0;
      padding: 8px 4px;
    }

    .headbar table,
    .rowbox table {
      width: 100%;
      table-layout: fixed;
      border-collapse: collapse;
    }

    .headbar td {
      text-align: center;
      color: #fff;
      font-size: 10px;
      padding: 0 2px;
    }

    .rowbox {
      border: 2px solid #c8c8c8;
      border-radius: 0 0 8px 8px;
      margin-top: 5px;
      padding: 7px 4px;
      page-break-inside: avoid;
    }

    .rowbox td {
      text-align: center;
      font-size: 9px;
      padding: 0 3px;
      vertical-align: middle;
      overflow-wrap: break-word;
    }

    .w-n {
      width: 7%;
    }

    .w-id {
      width: 11%;
    }

    .w-customer {
      width: 22%;
    }

    .w-car {
      width: 11%;
    }

    .w-company {
      width: 22%;
    }

    .w-date {
      width: 12%;
    }

    .w-time {
      width: 8%;
    }

    .w-price {
      width: 7%;
    }

    .empty {
      margin-top: 20px;
      text-align: center;
      font-size: 12px;
      color: #777;
    }
  </style>
</head>

<body>
  <div class="title">Car Expected Back ({{ $count }})</div>
  <div class="datebox">
    <table>
      <tr>
        <td>From date : &nbsp; {{ $from }}</td>
        <td class="end">To Date : &nbsp; {{ $to }}</td>
      </tr>
    </table>
  </div>
  @forelse($groups as $location => $reservations)
  <div class="loc">{{ $location }} ({{ $reservations->count() }})</div>
  <div class="headbar">
    <table>
      <tr>
        <td class="w-n">Number</td>
        <td class="w-id">Booking ID</td>
        <td class="w-customer">Customer Name</td>
        <td class="w-car">Car Number</td>
        <td class="w-company">Company Name</td>
        <td class="w-date">Drop off Date</td>
        <td class="w-time">Drop off Time</td>
        <td class="w-price">Total</td>
      </tr>
    </table>
  </div>
  @foreach($reservations as $idx => $reservation)
  @php
  $booking = $reservation->booking;
  $customer = $booking?->customer;
  $customerCompany = $booking?->customerCompany;
  $customerName = $customer?->name ?? $customer?->customer_name ?? $customerCompany?->supplier_name ?? $customerCompany?->company_name ?? $customerCompany?->name ?? '-';
  $dropoff = \Carbon\Carbon::parse($reservation->dropoff_end_datetime);
  @endphp
  <div class="rowbox">
    <table>
      <tr>
        <td class="w-n">{{ $loop->iteration }}</td>
        <td class="w-id">{{ $reservation->booking_id }}</td>
        <td class="w-customer">{{ $customerName }}</td>
        <td class="w-car">{{ $reservation->car?->number ?? '-' }}</td>
        <td class="w-company">{{ $reservation->car?->company?->name ?? '-' }}</td>
        <td class="w-date">{{ $dropoff->format('Y-m-d') }}</td>
        <td class="w-time">{{ $dropoff->format('H:i') }}</td>
        <td class="w-price">{{ number_format((float)($booking?->total ?? 0), 2) }} {{ $booking?->currency ?? '' }}</td>
      </tr>
    </table>
  </div>
  @endforeach
  @empty
  <div class="empty">No reservations are expected back within the selected dates.</div>
  @endforelse
</body>

</html>