<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\HouseController;
use App\Http\Controllers\Api\HouseSettingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RequestCommentController;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomEquipmentController;
use App\Http\Controllers\Api\RoomServiceController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StorageController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    // Authentication
    Route::post('login', 'login');
});

Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);

    // Roles and Permissions
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);

    // Users
    Route::resource('users', UserController::class);
    Route::post('/users/change-password/{id}', [UserController::class, 'changePassword']);
    Route::resource('notifications', NotificationController::class, [
        'only' => ['index', 'show', 'store', 'destroy']
    ]);
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

    // Contracts
    Route::resource('contracts', ContractController::class);

    // Requests
    Route::resource('requests', RequestController::class);
    Route::resource('request-comments', RequestCommentController::class);
});
