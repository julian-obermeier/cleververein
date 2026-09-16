<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use LogicException;

final class TenantContext
{
    private ?Tenant $tenant = null;
    private bool $supportMode = false;

    public function set(Tenant $tenant, bool $supportMode = false): void
    {
        $this->tenant = $tenant;
        $this->supportMode = $supportMode;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->supportMode = false;
    }

    public function tenant(): Tenant
    {
        return $this->tenant ?? throw new LogicException('Kein Mandantenkontext aktiv.');
    }

    public function id(): int { return $this->tenant()->getKey(); }
    public function hasTenant(): bool { return $this->tenant !== null; }
    public function isSupportMode(): bool { return $this->supportMode; }
}
