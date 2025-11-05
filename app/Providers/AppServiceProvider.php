<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Observers\AttachmentObserver;
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
        Attachment::observe(AttachmentObserver::class);
    }
}
