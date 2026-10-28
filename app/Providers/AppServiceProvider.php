<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\AuditEvent;
use App\Models\Rental;
use App\Models\Resale;
use App\Policies\AftercarePolicy;
use App\Policies\AreaPolicy;
use App\Policies\AuditEventPolicy;
use App\Policies\BrokerPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DealPolicy;
use App\Policies\ReassignmentPolicy;
use App\Policies\RequestPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Policies are declared once and shared by both departments, because
        // every department model answers its own department() (D-020).
        foreach ([Rental::class, Resale::class] as $namespace) {
            Gate::policy($namespace.'\Customer', CustomerPolicy::class);
            Gate::policy($namespace.'\Broker', BrokerPolicy::class);
            Gate::policy($namespace.'\Request', RequestPolicy::class);
            Gate::policy($namespace.'\Deal', DealPolicy::class);
            Gate::policy($namespace.'\Aftercare', AftercarePolicy::class);
            Gate::policy($namespace.'\Reassignment', ReassignmentPolicy::class);
        }

        Gate::policy(Area::class, AreaPolicy::class);
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
    }
}
