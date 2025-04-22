<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Models\Contract;
use App\Models\House;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    /**
     * Get dashboard statistics and information.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        // Get the authenticated user
        $user = auth()->user();
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';

        // Initialize query builders
        $housesQuery = House::query();
        $roomsQuery = Room::query();
        $tenantsQuery = User::where('role_id', function($query) {
            $query->select('id')->from('roles')->where('code', 'tenant');
        });
        $contractsQuery = Contract::query();

        // Apply access control based on role
        if ($isManager && !$isAdmin) {
            // Managers can only see statistics for houses they manage
            $managedHouseIds = $user->managedHouses()->pluck('id')->toArray();

            $housesQuery->whereIn('id', $managedHouseIds);
            $roomsQuery->whereIn('house_id', $managedHouseIds);
            $contractsQuery->whereHas('room', function($query) use ($managedHouseIds) {
                $query->whereIn('house_id', $managedHouseIds);
            });
            // Tenants filter is more complex - get tenants with contracts in managed houses
            $tenantsQuery->whereHas('contracts', function($query) use ($managedHouseIds) {
                $query->whereHas('room', function($subQuery) use ($managedHouseIds) {
                    $subQuery->whereIn('house_id', $managedHouseIds);
                });
            });
        } elseif (!$isAdmin && !$isManager) {
            // Regular users/tenants can only see their own data
            $housesQuery->whereHas('rooms.contracts', function($query) use ($user) {
                $query->where('user_id', $user->id);
            });
            $roomsQuery->whereHas('contracts', function($query) use ($user) {
                $query->where('user_id', $user->id);
            });
            $tenantsQuery->where('id', $user->id);
            $contractsQuery->where('user_id', $user->id);
        }

        // Gather statistics
        $stats = [
            'houses' => $housesQuery->count(),
            'rooms' => $roomsQuery->count(),
            'tenants' => $tenantsQuery->count(),
            'contracts' => $contractsQuery->count(),
        ];

        // Get system information
        $systemInfo = [
            'version' => config('app.version', '1.0.0'),
            'server_time' => now()->toDateTimeString(),
            'status' => 'active',
        ];

        $data = [
            'stats' => $stats,
            'system_info' => $systemInfo,
        ];

        return $this->sendResponse($data, 'Dashboard data retrieved successfully.');
    }
}
