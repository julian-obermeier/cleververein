<?php

namespace App\Http\Middleware;

use App\Models\FormDefinition;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublicFormTenant
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');
        $form = FormDefinition::withoutGlobalScope('tenant')
            ->with(['tenant', 'fields'])
            ->where('public_token', $token)
            ->where('form_type', 'public')
            ->where('status', 'published')
            ->firstOrFail();

        abort_unless($form->tenant && $form->tenant->status === 'active', 404);
        $this->tenant->set($form->tenant);
        $request->attributes->set('publicForm', $form);

        try {
            return $next($request);
        } finally {
            $this->tenant->clear();
        }
    }
}
