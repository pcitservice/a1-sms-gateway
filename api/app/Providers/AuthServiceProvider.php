<?php

namespace App\Providers;

use App\Models\Gateway;
use App\Policies\GatewayPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Gateway::class => GatewayPolicy::class,
    ];

    public function boot(): void
    {
        Gate::define('admin', fn ($user) => $user->is_admin === true);
    }
}
