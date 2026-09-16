<x-layouts.app :title="$form->name">
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('forms.index') }}" class="text-sm font-semibold text-blue-700 hover:underline">← Formulare</a>
        <div class="mt-2"><p class="text-sm font-semibold text-blue-700">Interne Einreichung</p><h1 class="mt-1 text-3xl font-bold tracking-tight">{{ $form->name }}</h1>@if($form->description)<p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $form->description }}</p>@endif</div>
        @if($errors->any())<div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="post" action="{{ route('forms.submit', $form) }}" enctype="multipart/form-data" class="cv-panel mt-6 p-5 sm:p-7">@csrf
            <div class="mb-6 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="cv-label">Mitgliedsbezug @if($form->require_member)<span class="text-red-600">*</span>@endif</label><select class="cv-input" name="member_id" @required($form->require_member)><option value="">Kein Mitglied / externer Vorgang</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected((string)old('member_id')===(string)$member->id)>{{ $member->member_number }} · {{ $member->person?->display_name }}</option>@endforeach</select></div>
                <div><label class="cv-label">Einreicher-Name</label><input class="cv-input" name="submitter_name" value="{{ old('submitter_name') }}"></div>
                <div><label class="cv-label">Einreicher-E-Mail</label><input class="cv-input" type="email" name="submitter_email" value="{{ old('submitter_email') }}"></div>
            </div>
            @include('forms._fields', ['form' => $form])
            <div class="mt-7 flex justify-end"><button class="cv-button-primary">Verbindlich einreichen</button></div>
        </form>
    </div>
</x-layouts.app>
