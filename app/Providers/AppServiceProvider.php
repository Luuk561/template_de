<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\View\Composers\MetaComposer;

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
        // Register MetaComposer for app layout and all components
        View::composer([
            'layouts.app',
            'components.layout.*',
            'components.meta.*',
            'components.analytics.*'
        ], MetaComposer::class);
    }
}
