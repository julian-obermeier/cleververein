<?php

namespace App\Http\Middleware;

use App\Models\FormDefinition;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFormEditable
{
    public function handle(Request $request, Closure $next): Response
    {
        $form = $request->route('form');

        if (! $form instanceof FormDefinition) {
            $form = FormDefinition::query()->findOrFail($form);
        }

        abort_if($form->status === 'archived', 409, 'Archivierte Formulare können nicht mehr verändert werden.');

        return $next($request);
    }
}
