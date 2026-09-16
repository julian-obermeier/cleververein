<x-layouts.app title="Segment · {{ $segment->name }}">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Mitgliedersegment</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">{{ $segment->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $segment->description ?: 'Dynamisch aus den gespeicherten Kriterien ermittelt.' }}</p>
        </div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('members.segments.index') }}" class="cv-button border border-slate-300 bg-white text-slate-700">Alle Segmente</a><a href="{{ route('members.index') }}" class="cv-button-primary">Mitgliederliste</a></div>
    </div>

    <section class="cv-panel mt-6 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-bold">Treffer</h2><p class="mt-1 text-sm text-slate-500">{{ $members->total() }} Mitglieder entsprechen aktuell diesem Segment.</p></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Mitglied</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Organisation</th><th class="px-5 py-3">Tags</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($members as $member)
                        @php($primary = $member->memberships->firstWhere('is_primary', true) ?? $member->memberships->first())
                        <tr><td class="px-5 py-4"><p class="font-semibold">{{ $member->person->display_name }}</p><p class="text-xs text-slate-500">{{ $member->member_number }} · {{ $member->person->email ?: 'keine E-Mail' }}</p></td><td class="px-5 py-4">{{ ucfirst($member->status) }}</td><td class="px-5 py-4">{{ $primary?->organizationUnit?->name ?: '—' }}</td><td class="px-5 py-4"><div class="flex flex-wrap gap-1">@forelse($member->tags as $tag)<span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium">{{ $tag->name }}</span>@empty<span class="text-slate-400">—</span>@endforelse</div></td><td class="px-5 py-4 text-right"><a class="font-semibold text-blue-700" href="{{ route('members.crm', $member) }}">CRM öffnen</a></td></tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Aktuell entspricht kein Mitglied diesem Segment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div class="mt-5">{{ $members->links() }}</div>
</x-layouts.app>
