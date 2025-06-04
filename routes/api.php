<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\HouseController;
use App\Http\Controllers\Api\HouseSettingController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MonthlyServiceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\RequestCommentController;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomEquipmentController;
use App\Http\Controllers\Api\RoomServiceController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServiceUsageController;
use App\Http\Controllers\Api\StorageController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    // Authentication
    Route::post('login', 'login');
});

Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    
    // Statistics
    Route::prefix('statistics')->group(function () {
        Route::get('/overview', [StatisticsController::class, 'overview']);
        Route::get('/contracts', [StatisticsController::class, 'contracts']);
        Route::get('/revenue', [StatisticsController::class, 'revenue']);
        Route::get('/services', [StatisticsController::class, 'services']);
        Route::get('/equipment', [StatisticsController::class, 'equipment']);
        Route::post('/export-report', [StatisticsController::class, 'exportReport']);
    });

    // Roles and Permissions
    Route::resource('roles', RoleController::class);

    // Users
    Route::resource('users', UserController::class);
    Route::post('/users/change-password/{id}', [UserController::class, 'changePassword']);
    Route::get('tenant/{tenantId}/managers', [UserController::class, 'getManagersForTenant']);
    Route::get('manager/{managerId}/tenants', [UserController::class, 'getTenantsForManager']);

    Route::resource('notifications', NotificationController::class);
    Route::post('notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);

    // Equipments
    Route::resource('equipments', EquipmentController::class);

    // Services
    Route::resource('services', ServiceController::class);

    // System Settings
    Route::resource('system-settings', SystemSettingController::class);

    // Houses
    Route::resource('houses', HouseController::class);
    Route::resource('house-settings', HouseSettingController::class);
    Route::resource('storages', StorageController::class);

    // Rooms
    Route::resource('rooms', RoomController::class);
    Route::resource('room-equipments', RoomEquipmentController::class);
    Route::resource('room-services', RoomServiceController::class);
    Route::resource('room-service-usages', ServiceUsageController::class);

    // Contracts
    Route::resource('contracts', ContractController::class);
    Route::get('/available-tenants', [ContractController::class, 'getAvailableTenants']);

    // Requests
    Route::resource('requests', RequestController::class);
    Route::resource('request-comments', RequestCommentController::class);

    // Invoices
    Route::resource('invoices', InvoiceController::class);
    Route::post('/invoices/{id}/update-payment', [InvoiceController::class, 'updatePaymentStatus']);
    
    // Payment Gateway
    Route::post('/payment/create-link-payment', [InvoiceController::class, 'createPayosPayment']);
    Route::post('/payment/receive-hook', [InvoiceController::class, 'verifyPayosPayment']);
    Route::post('/payment/update-cash', [InvoiceController::class, 'updateCashPayment']);

    // Config Management
    Route::resource('configs', ConfigController::class);
    Route::get('/configs/group/payos', [ConfigController::class, 'getPayosConfigs']);

    // Payment Methods
    Route::resource('payment-methods', PaymentMethodController::class);

    // Monthly Service Management
    Route::get('/monthly-services/houses', [MonthlyServiceController::class, 'getAvailableHouses']);
    Route::get('/monthly-services/rooms', [MonthlyServiceController::class, 'getRoomsNeedingUpdate']);
    Route::get('/monthly-services/rooms/{roomId}', [MonthlyServiceController::class, 'getRoomServices']);
    Route::post('/monthly-services/save', [MonthlyServiceController::class, 'saveRoomServiceUsage']);
});