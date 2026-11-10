<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
        Model::preventLazyLoading(! app()->isProduction());

        // Coarse gate for the team pages (route middleware + nav link); per-request rules live in LeaveRequestPolicy.
        Gate::define('review-leave-requests', fn (User $user): bool => $user->isManager() || $user->isAdmin());
    }
}
