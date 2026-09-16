<?php

namespace App\Services\Elections;

use App\Models\DelegateMandate;
use App\Models\Election;
use App\Models\ElectionCandidate;
use App\Models\ElectionCandidateResult;
use App\Models\ElectionOffice;
use App\Models\ElectionProtocol;
use App\Models\ElectionRound;
use App\Models\ElectionVoter;
use App\Models\FunctionAssignment;
use App\Models\Member;
use App\Support\Tenancy\TenantContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ElectionService
{
    public function __construct(private TenantContext $tenant) {}

    public function nextNumber(string $key, string $prefix, int $year): string
    {
        return DB::transaction(function () use ($key, $prefix, $year): string {
            $tenantId = $this->tenant->id();
            $row = DB::table('election_sequences')
                ->where('tenant_id', $tenantId)
                ->where('sequence_key', $key)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                DB::table('election_sequences')->insert([
                    'tenant_id' => $tenantId,
                    'sequence_key' => $key,
                    'year' => $year,
                    'next_value' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $value = 1;
            } else {
                $value = (int) $row->next_value;
                DB::table('election_sequences')->where('id', $row->id)->update([
                    'next_value' => $value + 1,
                    'updated_at' => now(),
                ]);
            }

            return sprintf('%s-%d-%06d', $prefix, $year, $value);
        });
    }

    public function seedVoters(Election $election): int
    {
        if ($election->status === 'finalized') {
            throw ValidationException::withMessages(['election' => 'Eine festgestellte Wahl kann nicht mehr neu besetzt werden.']);
        }

        if ($election->voter_basis === 'manual') {
            return 0;
        }

        $created = 0;
        if ($election->voter_basis === 'delegates') {
            $mandates = DelegateMandate::query()
                ->where('status', 'active')
                ->whereDate('starts_at', '<=', $election->election_date)
                ->where(function ($query) use ($election): void {
                    $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $election->election_date);
                })
                ->when($election->organization_unit_id, function ($query) use ($election): void {
                    $query->where(function ($scope) use ($election): void {
                        $scope->where('receiving_organization_unit_id', $election->organization_unit_id)
                            ->orWhereNull('receiving_organization_unit_id');
                    });
                })
                ->get();

            foreach ($mandates as $mandate) {
                $voter = ElectionVoter::query()->firstOrCreate(
                    ['election_id' => $election->id, 'member_id' => $mandate->member_id],
                    [
                        'delegate_mandate_id' => $mandate->id,
                        'source' => 'delegate',
                        'voting_weight' => $mandate->voting_weight,
                        'status' => 'eligible',
                    ],
                );
                if ($voter->wasRecentlyCreated) {
                    $created++;
                }
            }

            return $created;
        }

        $members = Member::query()->where('status', 'active');
        if ($election->organization_unit_id) {
            $organizationIds = DB::table('organization_closure')
                ->where('ancestor_id', $election->organization_unit_id)
                ->pluck('descendant_id')
                ->push($election->organization_unit_id)
                ->unique()
                ->values();
            $members->whereHas('memberships', function ($query) use ($organizationIds): void {
                $query->whereIn('organization_unit_id', $organizationIds)
                    ->where('status', 'active')
                    ->where(function ($period): void {
                        $period->whereNull('ends_at')->orWhereDate('ends_at', '>=', now()->toDateString());
                    });
            });
        }

        foreach ($members->pluck('id') as $memberId) {
            $voter = ElectionVoter::query()->firstOrCreate(
                ['election_id' => $election->id, 'member_id' => $memberId],
                ['source' => 'member', 'voting_weight' => 1, 'status' => 'eligible'],
            );
            if ($voter->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    public function createRound(ElectionOffice $office): ElectionRound
    {
        return DB::transaction(function () use ($office): ElectionRound {
            $lockedOffice = ElectionOffice::query()->whereKey($office->id)->lockForUpdate()->firstOrFail();
            $election = $lockedOffice->election;
            if ($election->status !== 'open') {
                throw ValidationException::withMessages(['election' => 'Wahlgänge können nur in einer geöffneten Wahl gestartet werden.']);
            }
            if ($lockedOffice->rounds()->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['round' => 'Für dieses Amt ist bereits ein Wahlgang geöffnet.']);
            }

            $roundNumber = ((int) $lockedOffice->rounds()->max('round_number')) + 1;
            if ($roundNumber > $lockedOffice->max_rounds) {
                throw ValidationException::withMessages(['round' => 'Die konfigurierte maximale Zahl an Wahlgängen ist erreicht.']);
            }

            return $lockedOffice->rounds()->create([
                'round_number' => $roundNumber,
                'status' => 'open',
                'eligible_weight' => $this->eligibleWeight($election),
                'result_status' => 'pending',
                'opened_at' => now(),
            ]);
        });
    }

    public function eligibleWeight(Election $election): float
    {
        $present = (float) $election->voters()->where('status', 'present')->sum('voting_weight');
        if (! $election->allow_proxies) {
            return round($present, 3);
        }

        $presentMemberIds = $election->voters()->where('status', 'present')->pluck('member_id');
        $proxyWeight = (float) $election->proxies()
            ->where('status', 'active')
            ->whereIn('proxy_member_id', $presentMemberIds)
            ->whereNotIn('grantor_member_id', $presentMemberIds)
            ->sum('voting_weight');

        return round($present + $proxyWeight, 3);
    }

    public function finalizeRound(ElectionRound $round, array $candidateVotes, float $abstainWeight, float $invalidWeight, int $userId): ElectionRound
    {
        return DB::transaction(function () use ($round, $candidateVotes, $abstainWeight, $invalidWeight, $userId): ElectionRound {
            $round = ElectionRound::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();
            if ($round->status !== 'open') {
                throw ValidationException::withMessages(['round' => 'Dieser Wahlgang ist nicht geöffnet.']);
            }

            $office = $round->office()->with('election')->firstOrFail();
            $candidates = $office->candidates()->whereIn('status', ['nominated', 'accepted'])->with('member.person')->get();
            if ($candidates->isEmpty()) {
                throw ValidationException::withMessages(['candidates' => 'Für dieses Amt sind keine wählbaren Kandidaturen vorhanden.']);
            }

            $normalizedVotes = [];
            foreach ($candidates as $candidate) {
                $votes = round(max(0, (float) ($candidateVotes[$candidate->id] ?? 0)), 3);
                $normalizedVotes[$candidate->id] = $votes;
            }
            $abstainWeight = round(max(0, $abstainWeight), 3);
            $invalidWeight = round(max(0, $invalidWeight), 3);
            if (! $office->allow_abstention && $abstainWeight > 0) {
                throw ValidationException::withMessages(['abstain_weight' => 'Enthaltungen sind für dieses Amt nicht zugelassen.']);
            }

            $candidateWeight = round(array_sum($normalizedVotes), 3);
            $castWeight = round($candidateWeight + $abstainWeight + $invalidWeight, 3);
            $eligibleWeight = $this->eligibleWeight($office->election);
            if ($castWeight > $eligibleWeight + 0.001) {
                throw ValidationException::withMessages(['votes' => 'Die erfasste Stimmenzahl übersteigt das verfügbare Stimmgewicht.']);
            }

            $basis = match ($office->majority_basis) {
                'cast_including_abstentions' => $candidateWeight + $abstainWeight,
                'eligible_weight' => $eligibleWeight,
                default => $candidateWeight,
            };
            $decision = $this->determineWinners($office, $normalizedVotes, round($basis, 3));

            ElectionCandidateResult::query()->where('election_round_id', $round->id)->delete();
            $rank = 0;
            $previousVotes = null;
            $ordered = collect($normalizedVotes)->sortDesc();
            foreach ($ordered as $candidateId => $votes) {
                if ($previousVotes === null || abs($votes - $previousVotes) > 0.0005) {
                    $rank++;
                }
                ElectionCandidateResult::query()->create([
                    'election_round_id' => $round->id,
                    'election_candidate_id' => (int) $candidateId,
                    'votes' => $votes,
                    'rank' => $rank,
                    'is_elected' => in_array((int) $candidateId, $decision['winner_ids'], true),
                ]);
                $previousVotes = $votes;
            }

            $round->update([
                'status' => 'closed',
                'eligible_weight' => $eligibleWeight,
                'cast_weight' => $castWeight,
                'invalid_weight' => $invalidWeight,
                'abstain_weight' => $abstainWeight,
                'result_status' => $decision['status'],
                'closed_at' => now(),
                'finalized_by' => $userId,
            ]);

            return $round->fresh(['results.candidate.member.person', 'office.election']);
        });
    }

    public function finalizeElection(Election $election, int $userId): ElectionProtocol
    {
        return DB::transaction(function () use ($election, $userId): ElectionProtocol {
            $election = Election::query()->whereKey($election->id)->lockForUpdate()->firstOrFail();
            if ($election->status === 'finalized') {
                throw ValidationException::withMessages(['election' => 'Diese Wahl wurde bereits endgültig festgestellt.']);
            }

            $election->load('offices.rounds.results.candidate.member.person');
            if ($election->offices->isEmpty()) {
                throw ValidationException::withMessages(['offices' => 'Vor der Feststellung muss mindestens ein Amt angelegt sein.']);
            }

            foreach ($election->offices as $office) {
                $decidedRound = $office->rounds->where('result_status', 'decided')->sortByDesc('round_number')->first();
                $electedCount = $decidedRound?->results?->where('is_elected', true)->count() ?? 0;
                if (! $decidedRound || $electedCount < $office->seats) {
                    throw ValidationException::withMessages(['election' => "Das Amt „{$office->name}“ ist noch nicht vollständig entschieden."]);
                }
            }

            $election->update([
                'status' => 'finalized',
                'finalized_at' => now(),
                'finalized_by' => $userId,
            ]);

            foreach ($election->offices as $office) {
                if ($office->sync_function_assignments && $office->function_definition_id) {
                    $this->syncOfficeAssignments($office);
                }
            }

            return $this->generateProtocol($election->fresh(), $userId);
        });
    }

    public function generateProtocol(Election $election, int $userId): ElectionProtocol
    {
        $election->load([
            'organizationUnit', 'governanceMeeting', 'finalizer',
            'voters.member.person', 'proxies.grantor.person', 'proxies.holder.person',
            'offices.functionDefinition', 'offices.candidates.member.person',
            'offices.rounds.results.candidate.member.person',
        ]);

        $snapshot = $this->snapshot($election);
        $pdf = $this->renderProtocolPdf($snapshot);
        $version = ((int) $election->protocols()->max('version')) + 1;
        $uuid = (string) Str::uuid();
        $path = "election-protocols/{$election->tenant_id}/{$uuid}.pdf";
        Storage::disk('local')->put($path, $pdf);

        return ElectionProtocol::query()->create([
            'public_id' => $uuid,
            'election_id' => $election->id,
            'version' => $version,
            'snapshot' => $snapshot,
            'disk' => 'local',
            'path' => $path,
            'size' => strlen($pdf),
            'generated_by' => $userId,
            'generated_at' => now(),
        ]);
    }

    private function determineWinners(ElectionOffice $office, array $votes, float $basis): array
    {
        arsort($votes, SORT_NUMERIC);
        if ($office->majority_type === 'highest_votes' || $office->majority_type === 'simple') {
            $candidateIds = array_keys($votes);
            if (count($candidateIds) < $office->seats) {
                return ['status' => 'no_result', 'winner_ids' => []];
            }
            $cutoff = array_values($votes)[$office->seats - 1] ?? null;
            if ($cutoff === null || $cutoff <= 0) {
                return ['status' => 'no_result', 'winner_ids' => []];
            }
            $above = array_keys(array_filter($votes, fn ($value) => $value > $cutoff + 0.0005));
            $atCutoff = array_keys(array_filter($votes, fn ($value) => abs($value - $cutoff) <= 0.0005));
            $remainingSeats = $office->seats - count($above);
            if (count($atCutoff) > $remainingSeats) {
                return ['status' => 'runoff', 'winner_ids' => array_map('intval', $above)];
            }

            return ['status' => 'decided', 'winner_ids' => array_map('intval', array_slice($candidateIds, 0, $office->seats))];
        }

        if ($basis <= 0) {
            return ['status' => 'no_result', 'winner_ids' => []];
        }

        $requiredRatio = $office->majority_type === 'two_thirds' ? (2 / 3) : 0.5;
        $qualified = [];
        foreach ($votes as $candidateId => $candidateVotes) {
            $passes = $office->majority_type === 'two_thirds'
                ? $candidateVotes + 0.0005 >= $basis * $requiredRatio
                : $candidateVotes > $basis * $requiredRatio;
            if ($passes) {
                $qualified[(int) $candidateId] = $candidateVotes;
            }
        }

        if (count($qualified) < $office->seats) {
            return ['status' => 'runoff', 'winner_ids' => array_slice(array_keys($qualified), 0, $office->seats)];
        }

        $winners = array_slice(array_keys($qualified), 0, $office->seats);
        if (count($qualified) > $office->seats) {
            $values = array_values($qualified);
            $cutoff = $values[$office->seats - 1];
            $next = $values[$office->seats] ?? null;
            if ($next !== null && abs($cutoff - $next) <= 0.0005) {
                return ['status' => 'runoff', 'winner_ids' => array_map('intval', array_slice($winners, 0, max(0, $office->seats - 1)))];
            }
        }

        return ['status' => 'decided', 'winner_ids' => array_map('intval', $winners)];
    }

    private function syncOfficeAssignments(ElectionOffice $office): void
    {
        $round = $office->rounds()->where('result_status', 'decided')->orderByDesc('round_number')->with('results')->firstOrFail();
        $winnerIds = $round->results->where('is_elected', true)->pluck('election_candidate_id');
        $memberIds = ElectionCandidate::query()->whereIn('id', $winnerIds)->pluck('member_id');
        $startsAt = $office->term_starts_at ?: $office->election->election_date;
        $endPrevious = $startsAt->copy()->subDay()->toDateString();

        FunctionAssignment::query()
            ->where('function_definition_id', $office->function_definition_id)
            ->where('organization_unit_id', $office->election->organization_unit_id)
            ->whereNull('ends_at')
            ->whereNotIn('member_id', $memberIds)
            ->update(['ends_at' => $endPrevious, 'updated_at' => now()]);

        foreach ($memberIds as $memberId) {
            FunctionAssignment::query()->firstOrCreate([
                'member_id' => $memberId,
                'function_definition_id' => $office->function_definition_id,
                'organization_unit_id' => $office->election->organization_unit_id,
                'starts_at' => $startsAt->toDateString(),
            ], [
                'ends_at' => $office->term_ends_at?->toDateString(),
                'notes' => 'Automatisch aus festgestellter Wahl '.$office->election->title,
            ]);
        }
    }

    private function snapshot(Election $election): array
    {
        return [
            'title' => $election->title,
            'date' => $election->election_date?->format('d.m.Y'),
            'organization' => $election->organizationUnit?->name,
            'meeting' => $election->governanceMeeting?->title,
            'status' => $election->status,
            'voter_basis' => $election->voter_basis,
            'allow_proxies' => $election->allow_proxies,
            'eligible_voters' => $election->voters->count(),
            'present_voters' => $election->voters->where('status', 'present')->count(),
            'present_weight' => $this->eligibleWeight($election),
            'finalized_at' => $election->finalized_at?->format('d.m.Y H:i'),
            'finalized_by' => $election->finalizer?->name,
            'offices' => $election->offices->map(function (ElectionOffice $office): array {
                return [
                    'name' => $office->name,
                    'seats' => $office->seats,
                    'voting_method' => $office->voting_method,
                    'majority_type' => $office->majority_type,
                    'majority_basis' => $office->majority_basis,
                    'rounds' => $office->rounds->map(function (ElectionRound $round): array {
                        return [
                            'number' => $round->round_number,
                            'eligible_weight' => $round->eligible_weight,
                            'cast_weight' => $round->cast_weight,
                            'abstain_weight' => $round->abstain_weight,
                            'invalid_weight' => $round->invalid_weight,
                            'result_status' => $round->result_status,
                            'results' => $round->results->map(fn (ElectionCandidateResult $result): array => [
                                'member' => $result->candidate?->member?->person?->display_name ?? 'Unbekannt',
                                'votes' => $result->votes,
                                'rank' => $result->rank,
                                'elected' => $result->is_elected,
                            ])->values()->all(),
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    private function renderProtocolPdf(array $snapshot): string
    {
        $officeHtml = '';
        foreach ($snapshot['offices'] as $office) {
            $officeHtml .= '<h2>'.e($office['name']).'</h2>';
            $officeHtml .= '<p>Sitze: '.e((string) $office['seats']).' · Verfahren: '.e($this->methodLabel($office['voting_method'])).' · Mehrheit: '.e($this->majorityLabel($office['majority_type'])).'</p>';
            foreach ($office['rounds'] as $round) {
                $officeHtml .= '<h3>Wahlgang '.e((string) $round['number']).'</h3><table><thead><tr><th>Kandidatur</th><th>Stimmen</th><th>Rang</th><th>Ergebnis</th></tr></thead><tbody>';
                foreach ($round['results'] as $result) {
                    $officeHtml .= '<tr><td>'.e($result['member']).'</td><td>'.e((string) $result['votes']).'</td><td>'.e((string) $result['rank']).'</td><td>'.($result['elected'] ? 'gewählt' : 'nicht gewählt').'</td></tr>';
                }
                $officeHtml .= '</tbody></table><p>Stimmgewicht verfügbar: '.e((string) $round['eligible_weight']).' · abgegeben: '.e((string) $round['cast_weight']).' · Enthaltungen: '.e((string) $round['abstain_weight']).' · ungültig: '.e((string) $round['invalid_weight']).'</p>';
            }
        }

        $html = '<!doctype html><html lang="de"><head><meta charset="utf-8"><style>@page{margin:18mm}body{font-family:DejaVu Sans,sans-serif;color:#0f172a;font-size:10pt}h1{font-size:19pt;margin:0 0 5mm}h2{font-size:14pt;margin-top:7mm;border-bottom:1px solid #cbd5e1;padding-bottom:2mm}h3{font-size:11pt;margin-top:5mm}p{line-height:1.45}table{width:100%;border-collapse:collapse;margin:2mm 0 3mm}th,td{border:1px solid #cbd5e1;padding:2mm;text-align:left}th{background:#f1f5f9}.meta{background:#f8fafc;padding:4mm;border:1px solid #e2e8f0}</style></head><body>'
            .'<h1>Wahlprotokoll · '.e($snapshot['title']).'</h1>'
            .'<div class="meta"><strong>Datum:</strong> '.e((string) $snapshot['date']).'<br><strong>Gliederung:</strong> '.e((string) ($snapshot['organization'] ?: 'mandantenweit')).'<br><strong>Wahlberechtigte:</strong> '.e((string) $snapshot['eligible_voters']).' · <strong>anwesend:</strong> '.e((string) $snapshot['present_voters']).' · <strong>Stimmgewicht:</strong> '.e((string) $snapshot['present_weight']).'<br><strong>Festgestellt:</strong> '.e((string) $snapshot['finalized_at']).' · '.e((string) $snapshot['finalized_by']).'</div>'
            .$officeHtml
            .'<p style="margin-top:10mm;color:#475569">Dieses Protokoll bildet die in cleververein gespeicherten aggregierten Wahlergebnisse ab. Bei geheimen Wahlen werden keine personenbezogenen Einzelstimmen gespeichert.</p>'
            .'</body></html>';

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function methodLabel(string $method): string
    {
        return ['secret' => 'geheim', 'open' => 'offen', 'acclamation' => 'Akklamation'][$method] ?? $method;
    }

    private function majorityLabel(string $type): string
    {
        return [
            'simple' => 'einfache Mehrheit',
            'absolute' => 'absolute Mehrheit',
            'two_thirds' => 'Zweidrittelmehrheit',
            'highest_votes' => 'höchste Stimmenzahl',
        ][$type] ?? $type;
    }
}
