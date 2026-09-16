<?php

namespace App\Services\Audit;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditService
{
    public function __construct(private TenantContext $context) {}

    public function record(string $event, ?Model $subject = null, array $old = [], array $new = [], ?string $reason = null): void
    {
        $request = request();
        DB::table('audit_logs')->insert([
            'tenant_id' => $this->context->hasTenant() ? $this->context->id() : null,
            'organization_unit_id' => null,
            'user_id' => auth()->id(),
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => $old ? json_encode($old, JSON_THROW_ON_ERROR) : null,
            'new_values' => $new ? json_encode($new, JSON_THROW_ON_ERROR) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 1000, ''),
            'request_id' => $request?->header('X-Request-ID') ?: Str::uuid()->toString(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
