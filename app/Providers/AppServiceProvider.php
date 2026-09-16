<?php

namespace App\Providers;

use App\Models\MemberTag;
use App\Services\Authorization\PermissionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, string $ability, array $arguments = []) {
            return app(PermissionService::class)->allows($user, $ability, $arguments[0] ?? null) ?: null;
        });

        View::composer('members.index', function ($view): void {
            $view->with('bulkTags', MemberTag::query()->where('is_active', true)->orderBy('name')->get());
        });
    }
}
