<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberType;
use App\Models\OrganizationUnit;
use App\Models\Person;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MemberSpreadsheetController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
    ) {}

    public function export(Request $request): StreamedResponse
    {
        $this->authorizePermission($request);
        $members = Member::query()
            ->with(['person', 'memberships.organizationUnit', 'memberships.memberType'])
            ->orderBy('member_number')
            ->get();

        return response()->streamDownload(function () use ($members): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Mitglieder');
            $headers = ['Mitgliedsnummer', 'Vorname', 'Nachname', 'E-Mail', 'Geburtsdatum', 'Status', 'Eintritt', 'Organisation', 'Mitgliedsart', 'Telefon', 'Mobil', 'Straße', 'PLZ', 'Ort'];
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:N1')->getFont()->setBold(true);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:N1');

            $row = 2;
            foreach ($members as $member) {
                $contact = $member->person->contact_data ?? [];
                $primary = $member->memberships->firstWhere('is_primary', true) ?? $member->memberships->first();
                $values = [
                    $member->member_number,
                    $member->person->first_name,
                    $member->person->last_name,
                    $member->person->email,
                    $member->person->birth_date?->format('Y-m-d'),
                    $member->status,
                    $member->joined_at?->format('Y-m-d'),
                    $primary?->organizationUnit?->name,
                    $primary?->memberType?->name ?? $primary?->membership_type,
                    $contact['phone'] ?? null,
                    $contact['mobile'] ?? null,
                    $contact['street'] ?? null,
                    $contact['postal_code'] ?? null,
                    $contact['city'] ?? null,
                ];
                foreach ($values as $columnIndex => $value) {
                    $column = chr(ord('A') + $columnIndex);
                    $sheet->setCellValueExplicit($column.$row, (string) ($value ?? ''), DataType::TYPE_STRING);
                }
                $row++;
            }

            foreach (range('A', 'N') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'mitglieder-'.now()->format('Y-m-d-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function template(Request $request): StreamedResponse
    {
        $this->authorizePermission($request);

        return response()->streamDownload(function (): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Importvorlage');
            $headers = ['Mitgliedsnummer', 'Vorname', 'Nachname', 'E-Mail', 'Geburtsdatum', 'Status', 'Eintritt', 'Organisation', 'Mitgliedsart', 'Telefon', 'Mobil', 'Straße', 'PLZ', 'Ort'];
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:N1')->getFont()->setBold(true);
            $sheet->fromArray([
                '', 'Max', 'Mustermann', 'max@example.de', '1990-01-01', 'aktiv', '2026-01-01', 'Hauptverein', 'Ordentliches Mitglied', '0641 123456', '', 'Musterstraße 1', '35390', 'Gießen',
            ], null, 'A2');
            $sheet->freezePane('A2');
            foreach (range('A', 'N') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'cleververein-mitglieder-importvorlage.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorizePermission($request);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']]);

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        if (count($rows) < 2) {
            return back()->withErrors(['file' => 'Die Excel-Datei enthält keine importierbaren Daten.']);
        }

        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), array_shift($rows));
        if (! in_array('first_name', $headers, true) || ! in_array('last_name', $headers, true)) {
            return back()->withErrors(['file' => 'Die Excel-Datei benötigt mindestens die Spalten Vorname und Nachname.']);
        }

        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($rows as $row) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $values = array_combine($headers, array_slice(array_pad($row, count($headers), null), 0, count($headers)));
            if (! is_array($values)) {
                $errors++;
                continue;
            }

            $result = $this->importRow($values);
            if ($result === 'created') {
                $created++;
            } elseif ($result === 'duplicate') {
                $skipped++;
            } else {
                $errors++;
            }
        }

        $this->audit->record('members.xlsx_imported', null, new: ['created' => $created, 'skipped_duplicates' => $skipped, 'errors' => $errors]);

        return back()->with('success', "Excel-Import abgeschlossen: {$created} angelegt, {$skipped} Dubletten übersprungen, {$errors} fehlerhafte Zeilen.");
    }

    private function importRow(array $values): string
    {
        $firstName = trim((string) ($values['first_name'] ?? ''));
        $lastName = trim((string) ($values['last_name'] ?? ''));
        if ($firstName === '' || $lastName === '') {
            return 'error';
        }

        $email = trim((string) ($values['email'] ?? '')) ?: null;
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'error';
        }

        $birthDate = $this->normalizeDate((string) ($values['birth_date'] ?? ''));
        $joinedAt = $this->normalizeDate((string) ($values['joined_at'] ?? ''));
        $memberNumber = trim((string) ($values['member_number'] ?? '')) ?: null;

        if ($memberNumber && Member::withTrashed()->where('member_number', $memberNumber)->exists()) {
            return 'duplicate';
        }
        if ($this->duplicateExists($firstName, $lastName, $email, $birthDate)) {
            return 'duplicate';
        }

        $organizationName = trim((string) ($values['organization'] ?? ''));
        $memberTypeName = trim((string) ($values['member_type'] ?? ''));
        $organization = $organizationName !== ''
            ? OrganizationUnit::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($organizationName)])->first()
            : null;
        if ($organizationName !== '' && ! $organization) {
            return 'error';
        }
        $memberType = $memberTypeName !== ''
            ? MemberType::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($memberTypeName)])->first()
            : null;

        try {
            DB::transaction(function () use ($values, $firstName, $lastName, $email, $birthDate, $joinedAt, $memberNumber, $organization, $memberType, $memberTypeName): void {
                $person = Person::query()->create([
                    'public_id' => Str::uuid(),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'birth_date' => $birthDate,
                    'contact_data' => array_filter([
                        'phone' => trim((string) ($values['phone'] ?? '')) ?: null,
                        'mobile' => trim((string) ($values['mobile'] ?? '')) ?: null,
                        'street' => trim((string) ($values['street'] ?? '')) ?: null,
                        'postal_code' => trim((string) ($values['postal_code'] ?? '')) ?: null,
                        'city' => trim((string) ($values['city'] ?? '')) ?: null,
                    ]),
                ]);
                $member = Member::query()->create([
                    'public_id' => Str::uuid(),
                    'person_id' => $person->id,
                    'member_number' => $memberNumber,
                    'status' => $this->normalizeMemberStatus((string) ($values['status'] ?? '')),
                    'joined_at' => $joinedAt,
                ]);
                if (! $member->member_number) {
                    $member->update(['member_number' => 'M-'.str_pad((string) $member->id, 6, '0', STR_PAD_LEFT)]);
                }

                if ($organization || $memberType || $memberTypeName !== '') {
                    $member->memberships()->create([
                        'organization_unit_id' => $organization?->id,
                        'member_type_id' => $memberType?->id,
                        'membership_type' => $memberType?->name ?? ($memberTypeName !== '' ? $memberTypeName : 'Ordentliches Mitglied'),
                        'status' => 'active',
                        'starts_at' => $joinedAt,
                        'is_primary' => true,
                    ]);
                }
            });
        } catch (Throwable) {
            return 'error';
        }

        return 'created';
    }

    private function duplicateExists(string $firstName, string $lastName, ?string $email, ?string $birthDate): bool
    {
        return Member::withTrashed()->whereHas('person', function ($query) use ($firstName, $lastName, $email, $birthDate): void {
            $query->where(function ($person) use ($firstName, $lastName, $birthDate): void {
                $person->whereRaw('LOWER(first_name) = ?', [mb_strtolower($firstName)])
                    ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($lastName)]);
                if ($birthDate) {
                    $person->whereDate('birth_date', $birthDate);
                }
            });
            if ($email) {
                $query->orWhereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
            }
        })->exists();
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d', 'd.m.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizeMemberStatus(string $value): string
    {
        return match (Str::lower(trim($value))) {
            'active', 'aktiv' => 'active',
            'pending', 'vorgemerkt' => 'pending',
            'inactive', 'inaktiv' => 'inactive',
            'resigned', 'ausgetreten' => 'resigned',
            'deceased', 'verstorben' => 'deceased',
            default => 'active',
        };
    }

    private function normalizeHeader(string $header): string
    {
        $header = Str::lower(trim($header));

        return match ($header) {
            'mitgliedsnummer', 'member_number', 'mitglieds-nr', 'mitgliedsnr' => 'member_number',
            'vorname', 'first_name' => 'first_name',
            'nachname', 'last_name' => 'last_name',
            'e-mail', 'email', 'mail' => 'email',
            'geburtsdatum', 'birth_date' => 'birth_date',
            'status' => 'status',
            'eintritt', 'eintrittsdatum', 'joined_at' => 'joined_at',
            'organisation', 'organization', 'organisationseinheit' => 'organization',
            'mitgliedsart', 'mitgliedschaftsart', 'member_type' => 'member_type',
            'telefon', 'phone' => 'phone',
            'mobil', 'mobile', 'handy' => 'mobile',
            'straße', 'strasse', 'street' => 'street',
            'plz', 'postal_code' => 'postal_code',
            'ort', 'city' => 'city',
            default => Str::snake($header),
        };
    }

    private function authorizePermission(Request $request): void
    {
        if ($request->user()->is_super_admin) {
            return;
        }
        abort_unless($this->permissions->allows($request->user(), 'members.import_export'), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }
}
