<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Resources\UserAdminResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminUserController extends Controller
{
    // Endpoint 1: List all users
    public function index()
    {
        $users = User::latest()->paginate(20);
        return UserAdminResource::collection($users);
    }

    // Endpoint 2: Toggle active/deactive status
    public function toggleStatus(User $user)
    {
        // Prevent admin from disabling themselves if you want
        if (Auth::id() === $user->id) {
            return response()->json(['message' => 'You cannot disable your own account'], 403);
        }

        $user->update([
            'is_active' => !$user->is_active
        ]);

        return response()->json([
            'message'   => 'User status updated successfully',
            'is_active' => $user->is_active,
            'user_name' => $user->first_name
        ]);
    }
}