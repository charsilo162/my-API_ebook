<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureVendorProfile
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !$user->vendorProfile) {
            return response()->json([
                'message' => 'You must complete vendor setup before performing this action.'
            ], 403);
        }

        return $next($request);
    }
}
