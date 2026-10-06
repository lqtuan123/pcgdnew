<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;

class LogoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Tạo symbolic link cho storage nếu chưa có
        if (!file_exists(public_path('storage'))) {
            $this->app->make('files')->link(
                storage_path('app/public'),
                public_path('storage')
            );
        }

        // Tạo thư mục logos nếu chưa có
        $logoPaths = [
            'public/logos/logo',
            'public/logos/favicon',
        ];

        foreach ($logoPaths as $path) {
            if (!Storage::exists($path)) {
                Storage::makeDirectory($path);
            }
        }
    }
}
