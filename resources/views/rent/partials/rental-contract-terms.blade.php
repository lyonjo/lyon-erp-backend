{{-- Include this partial from your rental contract PDF Blade with $contractTerms loaded in controller.
     This does not replace your existing PDF; it only prints saved tenant text. --}}
@if($contractTerms)
  <style>
    .rental-terms-columns { width: 100%; border-collapse: collapse; }
    .rental-terms-columns td { width: 50%; vertical-align: top; padding: 7px 12px; }
    .rental-terms-body { font-size: 10px; line-height: 1.45; white-space: pre-line; }
    .rental-terms-head { background: #438dc4; color: white; font-weight: bold; padding: 8px; }
  </style>
  <table class="rental-terms-columns"><tr>
    <td dir="ltr"><div class="rental-terms-head">TERMS &amp; CONDITIONS</div><div class="rental-terms-body">{{ $contractTerms->paragraph_en }}</div></td>
    <td dir="rtl"><div class="rental-terms-head">الشروط والأحكام</div><div class="rental-terms-body">{{ $contractTerms->paragraph_ar }}</div></td>
  </tr></table>
  <table class="rental-terms-columns"><tr>
    <td dir="ltr"><strong>Contract Terms</strong>@foreach($contractTerms->clauses as $clause)<div class="rental-terms-body">{{ $loop->iteration }}. {{ $clause->text_en }}</div>@endforeach</td>
    <td dir="rtl"><strong>شروط العقد</strong>@foreach($contractTerms->clauses as $clause)<div class="rental-terms-body">{{ $loop->iteration }} - {{ $clause->text_ar }}</div>@endforeach</td>
  </tr></table>
@endif
