<!doctype html>
<html lang="de"><head><meta charset="utf-8"><style>
@page{margin:18mm}body{font-family:DejaVu Sans,sans-serif;color:#0f172a;font-size:10pt;line-height:1.5}h1{font-size:21pt;margin:0 0 10mm}.sender{font-size:8pt;color:#64748b;border-bottom:1px solid #cbd5e1;margin-bottom:2mm}.recipient{width:85mm;margin-bottom:14mm}.box{margin-top:8mm;padding:5mm;background:#f8fafc}.amount{font-size:18pt;font-weight:bold}.footer{position:fixed;bottom:-10mm;left:0;right:0;border-top:1px solid #cbd5e1;padding-top:2mm;font-size:7.5pt;color:#64748b}
</style></head><body>
@php($recipient=$creditNote->recipient_snapshot ?? [])
<div style="text-align:right"><strong>{{ $settings?->creditor_name ?: $tenant->name }}</strong></div>
<div class="recipient"><div class="sender">{{ $settings?->creditor_name ?: $tenant->name }}@if($settings?->street) · {{ $settings->street }}@endif @if($settings?->postal_code || $settings?->city) · {{ $settings->postal_code }} {{ $settings->city }}@endif</div><strong>{{ $recipient['name'] ?? 'Empfänger' }}</strong><br>@if(!empty($recipient['street'])){{ $recipient['street'] }}<br>@endif {{ $recipient['postal_code'] ?? '' }} {{ $recipient['city'] ?? '' }}</div>
<h1>Gutschrift</h1>
<p><strong>{{ $creditNote->credit_number }}</strong><br>Datum: {{ $creditNote->credit_date?->format('d.m.Y') }}</p>
@if($creditNote->invoice)<p>Bezug: Rechnung <strong>{{ $creditNote->invoice->invoice_number ?: '#'.$creditNote->invoice->id }}</strong></p>@endif
<div class="box"><div>{{ $creditNote->reason }}</div><div class="amount">{{ number_format((float)$creditNote->amount,2,',','.') }} €</div></div>
<p>Der oben genannte Betrag wird dem zugehörigen Vorgang gutgeschrieben.</p>
<div class="footer">{{ $settings?->creditor_name ?: $tenant->name }}@if($settings?->tax_number) · St.-Nr. {{ $settings->tax_number }}@endif @if($settings?->vat_id) · USt-IdNr. {{ $settings->vat_id }}@endif</div>
</body></html>
