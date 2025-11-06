<?php

namespace App\Providers;

use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Policies\InvoicePolicy;
use App\Policies\QuoteLinePolicy;
use App\Policies\QuotePolicy;
// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Quote::class => QuotePolicy::class,
        QuoteLine::class => QuoteLinePolicy::class,
        Invoice::class => InvoicePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
