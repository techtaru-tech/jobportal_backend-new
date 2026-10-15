<?php

namespace App\Providers;

use App\Services\JobMatcher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request: the candidate's profile is read once and every
        // job on the page is scored against it.
        $this->app->scoped(JobMatcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
