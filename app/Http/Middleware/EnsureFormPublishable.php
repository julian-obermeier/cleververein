<?php

namespace App\Http\Middleware;

use App\Models\FormDefinition;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsureFormPublishable
{
    public function handle(Request $request, Closure $next): Response
    {
        $form = $request->route('form');

        if (! $form instanceof FormDefinition) {
            $form = FormDefinition::query()->findOrFail($form);
        }

        $form->loadMissing('fields');

        foreach ($form->fields as $field) {
            if (! in_array($field->field_type, ['select', 'radio', 'multiselect'], true)) {
                continue;
            }

            $options = collect($field->options ?? [])
                ->map(fn ($option) => trim((string) (is_array($option) ? ($option['value'] ?? $option['label'] ?? '') : $option)))
                ->filter()
                ->values();

            if ($options->isEmpty()) {
                throw ValidationException::withMessages([
                    'form' => "Das Auswahlfeld „{$field->label}“ benötigt vor der Veröffentlichung mindestens eine Auswahloption.",
                ]);
            }
        }

        return $next($request);
    }
}
