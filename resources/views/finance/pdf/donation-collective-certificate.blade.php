<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 15mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.3; color: #111; }
        h1 { font-size: 13pt; margin: 8px 0 3px; }
        h2 { font-size: 11pt; margin: 0 0 8px; }
        .muted { color: #444; font-size: 8pt; }
        .box { border: 1px solid #222; padding: 7px 9px; margin: 8px 0; }
        .grid { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .grid th, .grid td { border: 1px solid #222; padding: 5px 6px; vertical-align: top; }
        .grid th { background: #eee; font-size: 7.5pt; text-align: left; }
        .label { font-size: 7.4pt; color: #444; display: block; margin-bottom: 2px; }
        .check { display: inline-block; width: 11px; height: 11px; border: 1px solid #111; margin-right: 4px; text-align: center; line-height: 10px; }
        .signature { margin-top: 20px; border-top: 1px solid #222; padding-top: 4px; }
        .notice { margin-top: 11px; font-size: 7.6pt; line-height: 1.25; }
        .void { border: 2px solid #b91c1c; color: #b91c1c; padding: 6px; text-align: center; font-weight: bold; margin-bottom: 10px; }
        .page-break { page-break-before: always; }
        .right { text-align: right; }
    </style>
</head>
<body>
    @php($donor=$certificate->donor_snapshot ?? [])
    @php($recipient=$certificate->recipient_snapshot ?? [])
    @php($tax=$certificate->tax_snapshot ?? [])

    @if($certificate->status === 'voided')
        <div class="void">UNGÜLTIG / STORNIERT · {{ $certificate->voided_at?->format('d.m.Y H:i') }} · {{ $certificate->void_reason }}</div>
    @endif

    <div class="box">
        <span class="label">Aussteller – Bezeichnung und Anschrift der steuerbegünstigten Einrichtung</span>
        <strong>{{ $recipient['name'] ?? '—' }}</strong><br>
        {{ $recipient['street'] ?? '' }}<br>
        {{ trim(($recipient['postal_code'] ?? '').' '.($recipient['city'] ?? '')) }}
        @if(($recipient['country'] ?? 'DE') !== 'DE')<br>{{ $recipient['country'] }}@endif
    </div>

    <h1>Sammelbestätigung über Geldzuwendungen/Mitgliedsbeiträge</h1>
    <div class="muted">Zuwendungsbestätigung für eine steuerbegünstigte Einrichtung im Anwendungsbereich des § 10b EStG und § 5 Abs. 1 Nr. 9 KStG.</div>

    <div class="box">
        <span class="label">Name und Anschrift des Zuwendenden</span>
        <strong>{{ $donor['name'] ?? '—' }}</strong><br>
        {{ $donor['street'] ?? '' }}<br>
        {{ trim(($donor['postal_code'] ?? '').' '.($donor['city'] ?? '')) }}
        @if(($donor['country'] ?? 'DE') !== 'DE')<br>{{ $donor['country'] }}@endif
    </div>

    <table class="grid">
        <tr>
            <td style="width:31%"><span class="label">Gesamtbetrag – in Ziffern</span><strong>{{ number_format((float)$certificate->total_amount,2,',','.') }} €</strong></td>
            <td style="width:43%"><span class="label">Gesamtbetrag – in Buchstaben</span><strong>{{ $amountWords }}</strong></td>
            <td><span class="label">Zeitraum</span><strong>{{ $certificate->period_from->format('d.m.Y') }}–{{ $certificate->period_to->format('d.m.Y') }}</strong></td>
        </tr>
    </table>

    <p>Die in der beigefügten Anlage aufgeführten Zuwendungen ergeben zusammen den vorstehend bestätigten Gesamtbetrag. Für diese Einzelzuwendungen wurde keine weitere gültige Zuwendungsbestätigung ausgestellt.</p>
    <p>Die Anlage ist Bestandteil dieser Sammelbestätigung.</p>

    <div class="box">
        @if(($tax['notice_type'] ?? null) === '60a')
            <p><span class="check">X</span> Die satzungsmäßigen Voraussetzungen der steuerbegünstigten Einrichtung wurden durch das Finanzamt <strong>{{ $tax['tax_office'] ?? '—' }}</strong> unter der Steuernummer <strong>{{ $tax['tax_number'] ?? '—' }}</strong> mit Bescheid vom <strong>{{ isset($tax['notice_date']) ? \Illuminate\Support\Carbon::parse($tax['notice_date'])->format('d.m.Y') : '—' }}</strong> nach § 60a AO gesondert festgestellt.</p>
            <p>Nach der zugrunde liegenden Satzung werden folgende steuerbegünstigte Zwecke gefördert: <strong>{{ $tax['purposes'] ?? '—' }}</strong>.</p>
        @else
            <p><span class="check">X</span> Für die Förderung der angegebenen steuerbegünstigten Zwecke liegt ein <strong>{{ ($tax['notice_type'] ?? '') === 'koerperschaftsteuerbescheid' ? 'Körperschaftsteuerbescheid mit Anlage' : 'Freistellungsbescheid' }}</strong> des Finanzamts <strong>{{ $tax['tax_office'] ?? '—' }}</strong>, Steuernummer <strong>{{ $tax['tax_number'] ?? '—' }}</strong>, vom <strong>{{ isset($tax['notice_date']) ? \Illuminate\Support\Carbon::parse($tax['notice_date'])->format('d.m.Y') : '—' }}</strong>@if(!empty($tax['notice_years'])), für {{ $tax['notice_years'] }}@endif vor.</p>
            <p>Die Einrichtung ist für die bezeichneten Zwecke im dort festgestellten Umfang von Körperschaft- und Gewerbesteuer befreit. Geförderte Zwecke: <strong>{{ $tax['purposes'] ?? '—' }}</strong>.</p>
        @endif
        @if(!empty($tax['notice_reference']))<p class="muted">Akten-/Bescheidsreferenz: {{ $tax['notice_reference'] }}</p>@endif
    </div>

    <div class="box">
        <p>Die Zuwendungen werden ausschließlich für die vorstehend genannten steuerbegünstigten Zwecke eingesetzt.</p>
        @if(!($tax['membership_contributions_deductible'] ?? false))
            <p><span class="check">X</span> Soweit Mitgliedsbeiträge in der Anlage enthalten sind, wurden diese nur berücksichtigt, wenn sie nach den hinterlegten steuerlichen Stammdaten abzugsfähig sind.</p>
        @endif
    </div>

    <div class="signature">
        {{ $recipient['city'] ?? 'Ort' }}, {{ $certificate->issue_date->format('d.m.Y') }} &nbsp;&nbsp;&nbsp;&nbsp; Unterschrift des Zuwendungsempfängers
    </div>

    <div class="notice"><strong>Hinweis:</strong> Für unrichtige Bestätigungen oder eine zweckwidrige Verwendung können die gesetzlichen Haftungsfolgen nach § 10b Abs. 4 EStG, § 9 Abs. 3 KStG und § 9 Nr. 5 GewStG eintreten.</div>
    <div class="notice muted">Interne Dokumentnummer: {{ $certificate->certificate_number }} · Grundlage: bei Ausstellung gespeicherter Snapshot der Zuwendungs- und Steuerstammdaten.</div>

    <div class="page-break"></div>
    <h2>Anlage zur Sammelbestätigung {{ $certificate->certificate_number }}</h2>
    <p class="muted">Aufschlüsselung sämtlicher Einzelzuwendungen des bescheinigten Gesamtbetrags.</p>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:14%">Datum</th>
                <th style="width:18%">Art</th>
                <th>Zweck</th>
                <th style="width:12%">Aufwands-<br>verzicht</th>
                <th style="width:16%" class="right">Betrag</th>
            </tr>
        </thead>
        <tbody>
        @foreach($certificate->items as $item)
            <tr>
                <td>{{ $item->donation_date->format('d.m.Y') }}</td>
                <td>{{ match($item->donation_kind){'membership_contribution'=>'Mitgliedsbeitrag','expense_waiver'=>'Geldzuwendung aus Aufwandsverzicht',default=>'Geldzuwendung'} }}</td>
                <td>{{ $item->purpose }}</td>
                <td>{{ $item->expense_waiver ? 'Ja' : 'Nein' }}</td>
                <td class="right">{{ number_format((float)$item->amount,2,',','.') }} €</td>
            </tr>
        @endforeach
            <tr>
                <td colspan="4"><strong>Gesamtbetrag</strong></td>
                <td class="right"><strong>{{ number_format((float)$certificate->total_amount,2,',','.') }} €</strong></td>
            </tr>
        </tbody>
    </table>
    <p class="notice">Es handelt sich bei den einzelnen Zuwendungen um den Verzicht auf die Erstattung von Aufwendungen jeweils entsprechend der Kennzeichnung in der vorstehenden Anlage.</p>
</body>
</html>
