<?php

namespace App\Http\Controllers;

use App\Models\FormDefinition;
use App\Services\Forms\FormEngineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicFormController extends Controller
{
    public function __construct(private FormEngineService $engine) {}

    public function show(Request $request, string $token): View
    {
        /** @var FormDefinition $form */
        $form = $request->attributes->get('publicForm');
        $form->loadMissing('fields');

        return view('forms.public', ['form' => $form]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        /** @var FormDefinition $form */
        $form = $request->attributes->get('publicForm');
        if ($request->filled('website')) {
            throw ValidationException::withMessages(['form' => 'Die Einreichung konnte nicht verarbeitet werden.']);
        }
        $identityRules = $form->allow_anonymous ? 'nullable' : 'required';
        $data = $request->validate([
            'submitter_name' => [$identityRules, 'string', 'max:180'],
            'submitter_email' => [$identityRules, 'email:rfc', 'max:255'],
            'answers' => ['nullable', 'array'],
            'files' => ['nullable', 'array'],
            'website' => ['nullable', 'max:0'],
        ]);
        $submission = $this->engine->submit(
            $form,
            $request->input('answers', []),
            $request->file('files', []),
            null,
            null,
            $data['submitter_name'] ?? null,
            $data['submitter_email'] ?? null,
            ['source' => 'public'],
        );

        return redirect()->route('forms.public.show', $form->public_token)
            ->with('public_success', [
                'reference' => $submission->reference_number,
                'message' => $form->success_message ?: 'Vielen Dank. Ihre Angaben wurden erfolgreich übermittelt.',
            ]);
    }
}
