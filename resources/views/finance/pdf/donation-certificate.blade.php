<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22mm 18mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.4pt; line-height: 1.32; color: #111; }
        h1 { font-size: 13pt; margin: 8px 0 3px; }
        .muted { color: #444; font-size: 8.3pt; }
        .box { border: 1px solid #222; padding: 7px 9px; margin: 8px 0; }
        .grid { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .grid td { border: 1px solid #222; padding: 6px 7px; vertical-align: top; }
        .label { font-size: 7.5pt; color: #444; display: block; margin-bottom: 2px; }
        .check { display: inline-block; width: 11px; height: 11px; border: 1px solid #111; margin-right: 4px; text-align: center; line-height: 10px; }
        .signature { margin-top: 22px; border-top: 1px solid #222; padding-top: 4px; }
        .notice { margin-top: 14px; font-size: 7.8pt; line-height: 1.26; }
        .void { border: 2px solid #b91c1c; color: #b91c1c; padding: 6px; text-align: center; font-weight: bold; margin-bottom: 10px; }
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

    <h1>Bestätigung über Geldzuwendungen/Mitgliedsbeitrag</h1>
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
            <td style="width:31%"><span class="label">Betrag – in Ziffern</span><strong>{{ number_format((float)$certificate->amount,2,',','.') }} €</strong></td>
            <td style="width:43%"><span class="label">Betrag – in Buchstaben</span><strong>{{ $amountWords }}</strong></td>
            <td><span class="label">Tag der Zuwendung</span><strong>{{ $certificate->donation_date->format('d.m.Y') }}</strong></td>
        </tr>
    </table>

    <p>Es handelt sich um den Verzicht auf Erstattung von Aufwendungen:
        <span class="check">{{ $certificate->expense_waiver ? 'X' : '' }}</span> Ja
        &nbsp;&nbsp;
        <span class="check">{{ ! $certificate->expense_waiver ? 'X' : '' }}</span> Nein
    </p>

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
        <p>Die Zuwendung wird ausschließlich für die vorstehend genannten steuerbegünstigten Zwecke eingesetzt.</p>
        @if(!($tax['membership_contributions_deductible'] ?? false))
            <p><span class="check">X</span> Bei dieser Zuwendung handelt es sich nicht um einen steuerlich vom Abzug ausgeschlossenen Mitgliedsbeitrag.</p>
        @endif
    </div>

    <div class="signature">
        {{ $recipient['city'] ?? 'Ort' }}, {{ $certificate->issue_date->format('d.m.Y') }} &nbsp;&nbsp;&nbsp;&nbsp; Unterschrift des Zuwendungsempfängers
    </div>

    <div class="notice">
        <strong>Hinweis:</strong> Für unrichtige Bestätigungen oder eine zweckwidrige Verwendung können die gesetzlichen Haftungsfolgen nach § 10b Abs. 4 EStG, § 9 Abs. 3 KStG und § 9 Nr. 5 GewStG eintreten.
    </div>
    <div class="notice">
        Die steuerliche Anerkennung setzt außerdem voraus, dass der zugrunde liegende Freistellungs-/Körperschaftsteuerbescheid beziehungsweise die Feststellung nach § 60a AO innerhalb der gesetzlichen Gültigkeitsfristen nach § 63 Abs. 5 AO liegt.
    </div>
    <div class="notice muted">
        Interne Dokumentnummer: {{ $certificate->certificate_number }} · Grundlage: bei Ausstellung gespeicherter Snapshot der Zuwendungs- und Steuerstammdaten.
    </div>
</body>
</html>
