<?php

namespace App\Http\Controllers\API\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get summary statistics for the admin dashboard.
     */
    public function summary()
    {
        // Count total users
        $totalUsers = User::count();
        
        // Count active users
        $activeUsers = User::where('active', true)->count();
        
        // Count inactive users
        $inactiveUsers = User::where('active', false)->count();
        
        // Count users by role
        $usersByRole = [];
        $roles = Role::all();
        
        foreach ($roles as $role) {
            $usersByRole[] = [
                'role' => $role->name,
                'count' => User::role($role->name)->count()
            ];
        }
        
        // Count total roles and permissions
        $totalRoles = Role::count();
        $totalPermissions = Permission::count();
        
        // Get recent users
        $recentUsers = User::with('roles')
                          ->orderBy('created_at', 'desc')
                          ->limit(5)
                          ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'inactive_users' => $inactiveUsers,
                'users_by_role' => $usersByRole,
                'total_roles' => $totalRoles,
                'total_permissions' => $totalPermissions,
                'recent_users' => $recentUsers
            ]
        ]);
    }
}