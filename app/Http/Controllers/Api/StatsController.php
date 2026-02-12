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

        if (!$isAdmin && !$isVendor) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $vendorId = $isVendor ? $user->vendorProfile->id : null;

        // Total books
        $booksQuery = Book::query();
        if ($isVendor) {
            $booksQuery->where('vendor_id', $vendorId);
        }
        $totalBooks = $booksQuery->count();

        // Total physical (hard copy) and digital (soft copy) variants
        $physicalQuery = BookVariant::where('type', 'physical');
        $digitalQuery = BookVariant::where('type', 'digital');
        if ($isVendor) {
            $physicalQuery->whereHas('book', function ($query) use ($vendorId) {
                $query->where('vendor_id', $vendorId);
            });
            $digitalQuery->whereHas('book', function ($query) use ($vendorId) {
                $query->where('vendor_id', $vendorId);
            });
        }
        $totalPhysical = $physicalQuery->count();
        $totalDigital = $digitalQuery->count();

        // Total books bought (sold) - assuming paid orders
        $soldQuery = OrderItem::query()
            ->whereHas('order', function ($query) {
                $query->where('payment_status', 'paid');
            });
        if ($isVendor) {
            $soldQuery->whereHas('book', function ($query) use ($vendorId) {
                $query->where('vendor_id', $vendorId);
            });
        }
        $totalSold = $soldQuery->count();

        // Admin-only stats
        $totalUsers = 0;
        $totalVendors = 0;
        if ($isAdmin) {
            $totalUsers = User::where('type', 'user')->count();
            $totalVendors = User::where('type', 'vendor')->count();
        }

        // Prepare data for resource
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