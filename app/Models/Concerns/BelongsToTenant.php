<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $context = app(TenantContext::class);
            if ($context->hasTenant()) {
                $builder->where($builder->qualifyColumn('tenant_id'), $context->id());
            }
        });

        static::creating(function ($model): void {
            $context = app(TenantContext::class);
            if (! $context->hasTenant()) {
                throw new LogicException('Mandantenbezogene Datensätze benötigen einen aktiven Mandantenkontext.');
            }
            $model->tenant_id = $context->id();
        });
    }
}
