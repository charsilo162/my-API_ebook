<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Resources\StatsResource;
use App\Models\Book;
use App\Models\BookVariant;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StatsController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $isAdmin = $user->type === 'admin';
        $isVendor = $user->type === 'vendor';

        // Only admin or vendor can access
        if (!$isAdmin && !$isVendor) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Vendor ID (only for vendors)
        $vendorId = $isVendor ? $user->vendorProfile->id : null;

        // Total books
        $totalBooks = Book::when($isVendor, fn($q) => $q->where('vendor_id', $vendorId))->count();

        // Total physical and digital variants
        $totalPhysical = BookVariant::when($isVendor, fn($q) => 
            $q->whereHas('book', fn($b) => $b->where('vendor_id', $vendorId))
        )->where('type', 'physical')->count();

        $totalDigital = BookVariant::when($isVendor, fn($q) => 
            $q->whereHas('book', fn($b) => $b->where('vendor_id', $vendorId))
        )->where('type', 'digital')->count();

        // Total sold books (paid orders)
        $totalSold = OrderItem::when($isVendor, fn($q) => 
            $q->whereHas('book', fn($b) => $b->where('vendor_id', $vendorId))
        )->whereHas('order', fn($o) => $o->where('payment_status', 'paid'))->count();

        // Admin-only stats
        $totalUsers = $isAdmin ? User::where('type', 'user')->count() : 0;
        $totalVendors = $isAdmin ? User::where('type', 'vendor')->count() : 0;

        // Prepare data
        $data = [
            'total_books' => $totalBooks,
            'total_physical' => $totalPhysical,
            'total_digital' => $totalDigital,
            'total_sold' => $totalSold,
            'total_users' => $totalUsers,
            'total_vendors' => $totalVendors,
        ];

        return new StatsResource($data);
    }
}
