<?php

namespace App\Providers;

use App\Models\Team;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Cashier::useCustomerModel(Team::class);
        Cashier::calculateTaxes();
    }
}
