<?php

namespace App\Providers;

use App\Models\Contract;
use App\Observers\ContractObserver;
use App\Repositories\AuthRepository;
use App\Repositories\ConfigRepository;
use App\Repositories\ContractRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\EquipmentRepository;
use App\Repositories\Interfaces\AuthRepositoryInterface;
use App\Repositories\Interfaces\ConfigRepositoryInterface;
use App\Repositories\Interfaces\ContractRepositoryInterface;
use App\Repositories\Interfaces\DashboardRepositoryInterface;
use App\Repositories\Interfaces\EquipmentRepositoryInterface;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use App\Repositories\Interfaces\HouseSettingRepositoryInterface;
use App\Repositories\Interfaces\InvoiceRepositoryInterface;
use App\Repositories\Interfaces\MonthlyServiceRepositoryInterface;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;
use App\Repositories\Interfaces\RequestCommentRepositoryInterface;
use App\Repositories\Interfaces\RequestRepositoryInterface;
use App\Repositories\Interfaces\RoleRepositoryInterface;
use App\Repositories\Interfaces\RoomEquipmentRepositoryInterface;
use App\Repositories\Interfaces\RoomRepositoryInterface;
use App\Repositories\Interfaces\RoomServiceRepositoryInterface;
use App\Repositories\Interfaces\ServiceRepositoryInterface;
use App\Repositories\Interfaces\ServiceUsageRepositoryInterface;
use App\Repositories\Interfaces\StorageRepositoryInterface;
use App\Repositories\Interfaces\SystemSettingRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\HouseRepository;
use App\Repositories\HouseSettingRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\MonthlyServiceRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\RequestCommentRepository;
use App\Repositories\RequestRepository;
use App\Repositories\RoleRepository;
use App\Repositories\RoomEquipmentRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomServiceRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ServiceUsageRepository;
use App\Repositories\StorageRepository;
use App\Repositories\SystemSettingRepository;
use App\Repositories\UserRepository;
use App\Services\NotificationService;
use App\Repositories\Interfaces\StatisticsRepositoryInterface;
use App\Repositories\StatisticsRepository;
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
            return new NotificationService($app->make(NotificationRepositoryInterface::class));
        });

        // Đăng ký repositories
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(SystemSettingRepositoryInterface::class, SystemSettingRepository::class);
        $this->app->bind(EquipmentRepositoryInterface::class, EquipmentRepository::class);
        $this->app->bind(RoomRepositoryInterface::class, RoomRepository::class);
        $this->app->bind(StorageRepositoryInterface::class, StorageRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, ServiceRepository::class);
        $this->app->bind(PaymentMethodRepositoryInterface::class, PaymentMethodRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
        $this->app->bind(ConfigRepositoryInterface::class, ConfigRepository::class);
        $this->app->bind(ContractRepositoryInterface::class, ContractRepository::class);
        $this->app->bind(HouseRepositoryInterface::class, HouseRepository::class);
        $this->app->bind(HouseSettingRepositoryInterface::class, HouseSettingRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
        $this->app->bind(MonthlyServiceRepositoryInterface::class, MonthlyServiceRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(RequestCommentRepositoryInterface::class, RequestCommentRepository::class);
        $this->app->bind(RequestRepositoryInterface::class, RequestRepository::class);
        $this->app->bind(RoomEquipmentRepositoryInterface::class, RoomEquipmentRepository::class);
        $this->app->bind(RoomServiceRepositoryInterface::class, RoomServiceRepository::class);
        $this->app->bind(ServiceUsageRepositoryInterface::class, ServiceUsageRepository::class);
        $this->app->bind(StatisticsRepositoryInterface::class, StatisticsRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Contract::observe(ContractObserver::class);
    }
}
