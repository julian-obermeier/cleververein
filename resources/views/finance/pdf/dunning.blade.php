<!doctype html>
<html lang="de"><head><meta charset="utf-8"><style>
@page{margin:18mm}body{font-family:DejaVu Sans,sans-serif;color:#0f172a;font-size:10pt;line-height:1.5}h1{font-size:20pt;margin:0 0 10mm}.sender{font-size:8pt;color:#64748b;border-bottom:1px solid #cbd5e1;margin-bottom:2mm}.recipient{width:85mm;margin-bottom:14mm}.box{margin-top:8mm;padding:5mm;background:#f8fafc;border-left:3px solid #0f172a}.amount{font-size:15pt;font-weight:bold}.footer{position:fixed;bottom:-10mm;left:0;right:0;border-top:1px solid #cbd5e1;padding-top:2mm;font-size:7.5pt;color:#64748b}
</style></head><body>
@php($recipient=$invoice->recipient_snapshot ?? [])
<div style="text-align:right"><strong>{{ $settings?->creditor_name ?: $tenant->name }}</strong></div>
<div class="recipient"><div class="sender">{{ $settings?->creditor_name ?: $tenant->name }}@if($settings?->street) · {{ $settings->street }}@endif @if($settings?->postal_code || $settings?->city) · {{ $settings->postal_code }} {{ $settings->city }}@endif</div><strong>{{ $recipient['name'] ?? 'Empfänger' }}</strong><br>@if(!empty($recipient['street'])){{ $recipient['street'] }}<br>@endif {{ $recipient['postal_code'] ?? '' }} {{ $recipient['city'] ?? '' }}</div>
<h1>{{ $dunning->level }}. Mahnung</h1>
<p>Guten Tag,</p>
<p>zu unserer Rechnung <strong>{{ $invoice->invoice_number }}</strong> vom {{ $invoice->invoice_date?->format('d.m.Y') }} konnten wir bislang keinen vollständigen Zahlungsausgleich feststellen.</p>
<div class="box"><div>Offener Rechnungsbetrag</div><div class="amount">{{ number_format($invoice->open_amount,2,',','.') }} €</div>@if((float)$dunning->fee > 0)<div style="margin-top:2mm">Mahngebühr: {{ number_format((float)$dunning->fee,2,',','.') }} €</div><div><strong>Gesamt laut Mahnung: {{ number_format($invoice->open_amount + (float)$dunning->fee,2,',','.') }} €</strong></div>@endif</div>
<p>Bitte begleichen Sie den Betrag zeitnah und verwenden Sie als Verwendungszweck <strong>{{ $invoice->invoice_number }}</strong>.</p>
@if($dunning->notes)<p>{{ $dunning->notes }}</p>@endif
<p>Mit freundlichen Grüßen<br>{{ $settings?->creditor_name ?: $tenant->name }}</p>
<div class="footer">Mahndatum {{ $dunning->dunned_at?->format('d.m.Y') }} · Rechnung {{ $invoice->invoice_number }}</div>
</body></html>
