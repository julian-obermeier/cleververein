<?php

namespace App\Http\Controllers;

use App\Models\FunctionAssignment;
use App\Models\FunctionDefinition;
use App\Models\Household;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\OrganizationUnit;
use App\Models\Person;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use App\Support\Tenancy\TenantContext;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MemberToolsController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private TenantContext $tenant,
    ) {}

    public function settings(Request $request): View
    {
        $this->authorizePermission($request, 'members.master_data');

        return view('members.settings', [
            'memberTypes' => MemberType::query()->orderBy('sort_order')->orderBy('name')->get(),
            'functions' => FunctionDefinition::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function storeMemberType(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'members.master_data');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $type = MemberType::query()->create([
            ...$data,
            'code' => filled($data['code'] ?? null) ? Str::upper(Str::slug($data['code'], '_')) : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => true,
        ]);
        $this->audit->record('member_type.created', $type, new: ['name' => $type->name]);

        return back()->with('success', 'Mitgliedsart wurde angelegt.');
    }

    public function toggleMemberType(Request $request, MemberType $memberType): RedirectResponse
    {
        $this->authorizePermission($request, 'members.master_data');
        $memberType->update(['is_active' => ! $memberType->is_active]);
        $this->audit->record('member_type.updated', $memberType, new: ['is_active' => $memberType->is_active]);

        return back()->with('success', 'Mitgliedsart wurde aktualisiert.');
    }

    public function storeFunctionDefinition(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'members.master_data');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $function = FunctionDefinition::query()->create([
            ...$data,
            'code' => filled($data['code'] ?? null) ? Str::upper(Str::slug($data['code'], '_')) : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => true,
        ]);
        $this->audit->record('function_definition.created', $function, new: ['name' => $function->name]);

        return back()->with('success', 'Funktion wurde angelegt.');
    }

    public function toggleFunctionDefinition(Request $request, FunctionDefinition $function): RedirectResponse
    {
        $this->authorizePermission($request, 'members.master_data');
        $function->update(['is_active' => ! $function->is_active]);
        $this->audit->record('function_definition.updated', $function, new: ['is_active' => $function->is_active]);

        return back()->with('success', 'Funktion wurde aktualisiert.');
    }

    public function storeFunctionAssignment(Request $request, Member $member): RedirectResponse
    {
        $data = $request->validate([
            'function_definition_id' => ['required', 'integer'],
            'organization_unit_id' => ['nullable', 'integer'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $definition = FunctionDefinition::query()->where('is_active', true)->findOrFail($data['function_definition_id']);
        $organization = filled($data['organization_unit_id'] ?? null)
            ? OrganizationUnit::query()->findOrFail($data['organization_unit_id'])
            : null;
        $this->authorizePermission($request, 'members.functions', $organization?->id);

        $assignment = $member->functionAssignments()->create([
            'function_definition_id' => $definition->id,
            'organization_unit_id' => $organization?->id,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit->record('function_assignment.created', $assignment, new: ['function' => $definition->name]);

        return back()->with('success', 'Funktion wurde zugewiesen.');
    }

    public function destroyFunctionAssignment(Request $request, Member $member, FunctionAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->member_id === $member->id, 404);
        $this->authorizePermission($request, 'members.functions', $assignment->organization_unit_id);
        $this->audit->record('function_assignment.deleted', $assignment);
        $assignment->delete();

        return back()->with('success', 'Funktionszuweisung wurde entfernt.');
    }

    public function storeHousehold(Request $request, Member $member): RedirectResponse
    {
        $this->authorizePermission($request, 'members.households');
        $data = $request->validate([
            'household_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:150'],
            'relationship' => ['nullable', 'string', 'max:80'],
            'is_primary_contact' => ['nullable', 'boolean'],
        ]);

        $household = filled($data['household_id'] ?? null)
            ? Household::query()->findOrFail($data['household_id'])
            : Household::query()->create([
                'public_id' => Str::uuid(),
                'name' => ($data['name'] ?? null) ?: $member->person->last_name.' Haushalt',
            ]);

        if (! $household->members()->whereKey($member->id)->exists()) {
            $household->members()->attach($member->id, [
                'tenant_id' => $this->tenant->id(),
                'relationship' => $data['relationship'] ?? null,
                'is_primary_contact' => (bool) ($data['is_primary_contact'] ?? false),
            ]);
        }
        $this->audit->record('household.member_added', $household, new: ['member_id' => $member->id]);

        return back()->with('success', 'Haushaltszuordnung wurde gespeichert.');
    }

    public function detachHousehold(Request $request, Member $member, Household $household): RedirectResponse
    {
        $this->authorizePermission($request, 'members.households');
        $household->members()->detach($member->id);
        $this->audit->record('household.member_removed', $household, old: ['member_id' => $member->id]);

        return back()->with('success', 'Haushaltszuordnung wurde entfernt.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizePermission($request, 'members.import_export');
        $members = Member::query()
            ->with(['person', 'memberships.organizationUnit', 'memberships.memberType'])
            ->orderBy('member_number')
            ->get();

        return response()->streamDownload(function () use ($members): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Mitgliedsnummer', 'Vorname', 'Nachname', 'E-Mail', 'Geburtsdatum', 'Status', 'Eintritt', 'Organisation', 'Mitgliedsart', 'Telefon', 'Mobil', 'Straße', 'PLZ', 'Ort'], ';');
            foreach ($members as $member) {
                $contact = $member->person->contact_data ?? [];
                $primary = $member->memberships->firstWhere('is_primary', true) ?? $member->memberships->first();
                fputcsv($out, [
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
                ], ';');
            }
            fclose($out);
        }, 'mitglieder-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'members.import_export');
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $handle = fopen($request->file('file')->getRealPath(), 'rb');
        abort_unless($handle !== false, 422, 'CSV-Datei konnte nicht geöffnet werden.');
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = substr_count((string) $firstLine, ';') >= substr_count((string) $firstLine, ',') ? ';' : ',';
        $headers = fgetcsv($handle, 0, $delimiter) ?: [];
        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), $headers);
        abort_if($headers === [] || ! in_array('first_name', $headers, true) || ! in_array('last_name', $headers, true), 422, 'Die CSV-Datei benötigt mindestens die Spalten Vorname und Nachname.');

        $created = 0;
        $skipped = 0;
        $errors = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $normalizedRow = array_slice(array_pad($row, count($headers), null), 0, count($headers));
            $values = array_combine($headers, $normalizedRow);
            if (! is_array($values)) {
                $errors++;

                continue;
            }

            $firstName = trim((string) ($values['first_name'] ?? ''));
            $lastName = trim((string) ($values['last_name'] ?? ''));
            if ($firstName === '' || $lastName === '') {
                $errors++;

                continue;
            }

            $email = trim((string) ($values['email'] ?? '')) ?: null;
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors++;

                continue;
            }

            $rawBirthDate = trim((string) ($values['birth_date'] ?? ''));
            $birthDate = $this->normalizeDate($rawBirthDate);
            if ($rawBirthDate !== '' && $birthDate === null) {
                $errors++;

                continue;
            }

            $rawJoinedAt = trim((string) ($values['joined_at'] ?? ''));
            $joinedAt = $this->normalizeDate($rawJoinedAt);
            if ($rawJoinedAt !== '' && $joinedAt === null) {
                $errors++;

                continue;
            }

            $memberNumber = trim((string) ($values['member_number'] ?? '')) ?: null;
            if ($memberNumber && Member::withTrashed()->where('member_number', $memberNumber)->exists()) {
                $skipped++;

                continue;
            }

            if ($this->duplicateExists($firstName, $lastName, $email, $birthDate)) {
                $skipped++;

                continue;
            }

            $organizationName = trim((string) ($values['organization'] ?? ''));
            $memberTypeName = trim((string) ($values['member_type'] ?? ''));
            $organization = $organizationName !== '' ? OrganizationUnit::query()->where('name', $organizationName)->first() : null;
            $memberType = $memberTypeName !== '' ? MemberType::query()->where('name', $memberTypeName)->first() : null;
            $status = $this->normalizeMemberStatus((string) ($values['status'] ?? ''));

            try {
                DB::transaction(function () use ($values, $firstName, $lastName, $email, $birthDate, $joinedAt, $memberNumber, $organization, $memberType, $memberTypeName, $status): void {
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
                        'status' => $status,
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
                $created++;
            } catch (Throwable) {
                $errors++;
            }
        }
        fclose($handle);

        $this->audit->record('members.imported', null, new: ['created' => $created, 'skipped_duplicates' => $skipped, 'errors' => $errors]);

        return back()->with('success', "Import abgeschlossen: {$created} angelegt, {$skipped} Dubletten übersprungen, {$errors} fehlerhafte Zeilen.");
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
        $header = Str::lower(trim(str_replace("\xEF\xBB\xBF", '', $header)));

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

    private function authorizePermission(Request $request, string $permission, ?int $organizationId = null): void
    {
        if ($request->user()->is_super_admin) {
            return;
        }
        abort_unless($this->permissions->allows($request->user(), $permission, $organizationId), 403, 'Für diese Aktion fehlt die Berechtigung.');
    }
}
