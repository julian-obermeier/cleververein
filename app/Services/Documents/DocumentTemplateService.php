<?php

namespace App\Services\Documents;

use App\Models\DocumentTemplate;
use App\Models\Member;
use App\Support\Tenancy\TenantContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DocumentTemplateService
{
    public function __construct(private TenantContext $tenant) {}

    public function placeholders(): array
    {
        return [
            '{{datum.heute}}' => 'Aktuelles Datum',
            '{{verein.name}}' => 'Name des Vereins / Mandanten',
            '{{verein.slug}}' => 'Mandanten-Kennung',
            '{{mitglied.nummer}}' => 'Mitgliedsnummer',
            '{{mitglied.status}}' => 'Mitgliedsstatus',
            '{{mitglied.eintritt}}' => 'Eintrittsdatum',
            '{{mitglied.austritt}}' => 'Austrittsdatum',
            '{{mitglied.anrede}}' => 'Anrede',
            '{{mitglied.titel}}' => 'Titel',
            '{{mitglied.vorname}}' => 'Vorname',
            '{{mitglied.nachname}}' => 'Nachname',
            '{{mitglied.name}}' => 'Vollständiger Name',
            '{{mitglied.email}}' => 'E-Mail-Adresse',
            '{{mitglied.geburtsdatum}}' => 'Geburtsdatum',
            '{{mitglied.strasse}}' => 'Straße',
            '{{mitglied.plz}}' => 'Postleitzahl',
            '{{mitglied.ort}}' => 'Ort',
            '{{mitglied.land}}' => 'Land',
            '{{mitglied.telefon}}' => 'Telefon',
            '{{mitglied.mobil}}' => 'Mobilnummer',
            '{{mitglied.organisation}}' => 'Primäre Organisation',
            '{{mitglied.mitgliedsart}}' => 'Primäre Mitgliedsart',
        ];
    }

    public function normalizeLayout(array $layout): array
    {
        $blocks = $layout['blocks'] ?? $layout;
        if (! is_array($blocks)) {
            throw new InvalidArgumentException('Das Dokumentlayout ist ungültig.');
        }

        $normalized = [];
        foreach (array_slice($blocks, 0, 150) as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = in_array($block['type'] ?? 'text', ['text', 'line'], true) ? ($block['type'] ?? 'text') : 'text';
            $normalized[] = [
                'id' => Str::limit((string) ($block['id'] ?? 'block-'.$index), 80, ''),
                'type' => $type,
                'x' => $this->number($block['x'] ?? 15, 0, 297),
                'y' => $this->number($block['y'] ?? 15, 0, 420),
                'w' => $this->number($block['w'] ?? 80, 5, 297),
                'h' => $this->number($block['h'] ?? 12, 1, 420),
                'text' => Str::limit((string) ($block['text'] ?? ''), 10000, ''),
                'font_size' => $this->number($block['font_size'] ?? 11, 6, 48),
                'font_weight' => in_array((string) ($block['font_weight'] ?? '400'), ['400', '600', '700'], true) ? (string) $block['font_weight'] : '400',
                'align' => in_array($block['align'] ?? 'left', ['left', 'center', 'right'], true) ? $block['align'] : 'left',
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($block['color'] ?? '#0f172a')) ? $block['color'] : '#0f172a',
            ];
        }

        return ['blocks' => $normalized];
    }

    public function context(?Member $member = null): array
    {
        $tenant = $this->tenant->tenant();
        $contact = $member?->person?->contact_data ?? [];
        $primary = $member?->memberships?->firstWhere('is_primary', true) ?? $member?->memberships?->first();

        return [
            '{{datum.heute}}' => now()->format('d.m.Y'),
            '{{verein.name}}' => $tenant->name,
            '{{verein.slug}}' => $tenant->slug,
            '{{mitglied.nummer}}' => $member?->member_number ?? '',
            '{{mitglied.status}}' => $this->statusLabel($member?->status),
            '{{mitglied.eintritt}}' => $member?->joined_at?->format('d.m.Y') ?? '',
            '{{mitglied.austritt}}' => $member?->left_at?->format('d.m.Y') ?? '',
            '{{mitglied.anrede}}' => $member?->person?->salutation ?? '',
            '{{mitglied.titel}}' => $member?->person?->title ?? '',
            '{{mitglied.vorname}}' => $member?->person?->first_name ?? '',
            '{{mitglied.nachname}}' => $member?->person?->last_name ?? '',
            '{{mitglied.name}}' => $member?->person?->display_name ?? '',
            '{{mitglied.email}}' => $member?->person?->email ?? '',
            '{{mitglied.geburtsdatum}}' => $member?->person?->birth_date?->format('d.m.Y') ?? '',
            '{{mitglied.strasse}}' => (string) ($contact['street'] ?? ''),
            '{{mitglied.plz}}' => (string) ($contact['postal_code'] ?? ''),
            '{{mitglied.ort}}' => (string) ($contact['city'] ?? ''),
            '{{mitglied.land}}' => (string) ($contact['country'] ?? ''),
            '{{mitglied.telefon}}' => (string) ($contact['phone'] ?? ''),
            '{{mitglied.mobil}}' => (string) ($contact['mobile'] ?? ''),
            '{{mitglied.organisation}}' => $primary?->organizationUnit?->name ?? '',
            '{{mitglied.mitgliedsart}}' => $primary?->memberType?->name ?? $primary?->membership_type ?? '',
        ];
    }

    public function renderHtml(DocumentTemplate $template, ?Member $member = null): string
    {
        $layout = $this->normalizeLayout($template->layout ?? []);
        $context = $this->context($member);
        $orientation = $template->orientation === 'landscape' ? 'landscape' : 'portrait';
        $pageSize = in_array($template->page_size, ['A4', 'A5', 'Letter'], true) ? $template->page_size : 'A4';
        $blocks = '';

        foreach ($layout['blocks'] as $block) {
            $x = $block['x'];
            $y = $block['y'];
            $w = $block['w'];
            $h = $block['h'];
            if ($block['type'] === 'line') {
                $blocks .= '<div style="position:absolute;left:'.$x.'mm;top:'.$y.'mm;width:'.$w.'mm;border-top:1px solid '.e($block['color']).';"></div>';
                continue;
            }

            $text = strtr($block['text'], $context);
            $safeText = nl2br(e($text), false);
            $blocks .= '<div style="position:absolute;overflow:hidden;left:'.$x.'mm;top:'.$y.'mm;width:'.$w.'mm;height:'.$h.'mm;font-size:'.$block['font_size'].'pt;font-weight:'.$block['font_weight'].';text-align:'.$block['align'].';color:'.e($block['color']).';line-height:1.25;">'.$safeText.'</div>';
        }

        return '<!doctype html><html lang="de"><head><meta charset="utf-8"><style>@page{size:'.$pageSize.' '.$orientation.';margin:0}html,body{margin:0;padding:0;font-family:DejaVu Sans,sans-serif;color:#0f172a}.page{position:relative;width:100%;height:100%;}</style></head><body><div class="page">'.$blocks.'</div></body></html>';
    }

    public function renderPdf(DocumentTemplate $template, ?Member $member = null): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->renderHtml($template, $member), 'UTF-8');
        $dompdf->setPaper($template->page_size ?: 'A4', $template->orientation === 'landscape' ? 'landscape' : 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function number(mixed $value, float $min, float $max): float
    {
        $number = is_numeric($value) ? (float) $value : $min;

        return round(max($min, min($max, $number)), 2);
    }

    private function statusLabel(?string $status): string
    {
        return [
            'active' => 'Aktiv',
            'pending' => 'Vorgemerkt',
            'inactive' => 'Inaktiv',
            'resigned' => 'Ausgetreten',
            'deceased' => 'Verstorben',
        ][$status ?? ''] ?? ($status ?? '');
    }
}
