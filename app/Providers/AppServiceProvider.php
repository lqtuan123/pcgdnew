<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use App\Helpers\NumberFormatHelper;
use App\Models\Person;
use App\Observers\PersonObserver;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(\App\Services\SurveyLockService::class, fn($app) => new \App\Services\SurveyLockService());

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Đăng ký helper function để sử dụng trong views
        Blade::directive('formatNumber', function ($expression) {
            return "<?php echo App\Helpers\NumberFormatHelper::format($expression); ?>";
        });

        Blade::directive('formatPercent', function ($expression) {
            return "<?php echo App\Helpers\NumberFormatHelper::formatPercent($expression); ?>";
        });

        Blade::directive('formatCurrency', function ($expression) {
            return "<?php echo App\Helpers\NumberFormatHelper::formatCurrency($expression); ?>";
        });

        Person::observe(PersonObserver::class);
    }
}
