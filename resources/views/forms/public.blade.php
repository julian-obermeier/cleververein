<x-layouts.guest :title="$form->name.' · cleververein'">
    <section class="w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
        <header class="border-b border-slate-200 px-6 py-5 sm:px-8"><x-logo class="text-xl text-brand-950" /><div class="mt-5"><p class="text-sm font-semibold text-blue-700">Online-Formular</p><h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $form->name }}</h1>@if($form->description)<p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $form->description }}</p>@endif</div></header>
        <div class="p-6 sm:p-8">
            @if(session('public_success'))
                @php($success = session('public_success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5"><div class="flex gap-3"><div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 text-xl text-emerald-700">✓</div><div><h2 class="font-bold text-emerald-950">Einreichung erfolgreich</h2><p class="mt-1 text-sm text-emerald-900">{{ $success['message'] }}</p><p class="mt-3 text-sm font-semibold text-emerald-950">Vorgangsnummer: {{ $success['reference'] }}</p></div></div></div>
            @else
                @if($errors->any())<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <form method="post" action="{{ route('forms.public.store', $form->public_token) }}" enctype="multipart/form-data">@csrf
                    <div class="absolute left-[-10000px] top-auto h-px w-px overflow-hidden" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                    @unless($form->allow_anonymous)
                        <div class="mb-6 grid gap-4 sm:grid-cols-2"><div><label class="cv-label">Name <span class="text-red-600">*</span></label><input class="cv-input" name="submitter_name" value="{{ old('submitter_name') }}" required autocomplete="name"></div><div><label class="cv-label">E-Mail-Adresse <span class="text-red-600">*</span></label><input class="cv-input" type="email" name="submitter_email" value="{{ old('submitter_email') }}" required autocomplete="email"></div></div>
                    @endunless
                    @include('forms._fields', ['form' => $form])
                    <div class="mt-7"><p class="mb-4 text-xs text-slate-500">Mit dem Absenden werden die eingegebenen Angaben an die zuständige Organisation übermittelt und dort zur Bearbeitung dieses Vorgangs gespeichert.</p><button class="cv-button-primary w-full sm:w-auto">Formular absenden</button></div>
                </form>
            @endif
        </div>
    </section>
</x-layouts.guest>
