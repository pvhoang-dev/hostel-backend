<?php

namespace App\Providers;

use App\Models\Contract;
use App\Observers\ContractObserver;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\UserRepository;
use App\Services\NotificationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Đăng ký NotificationService
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService();
        });
        
        // Đăng ký repositories
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Contract::observe(ContractObserver::class);
    }
}
