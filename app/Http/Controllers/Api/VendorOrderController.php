<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Resources\OrderResource;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VendorOrderController extends Controller
{
    /**
     * List all physical orders assigned to this vendor.
     */
  public function index()
        {
            // We filter orders where the book variant belongs to a book owned by the vendor
            $orders = Order::whereHas('items.variant.book', function ($query) {
                $query->where('vendor_id', Auth::id());
            })
            ->with(['items.variant.book', 'user']) // Eager load the chain
            ->latest()
            ->paginate(15);

            return OrderResource::collection($orders);
        }

    /**
     * Update the status of a specific order.
     */
public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled'
        ]);

        return DB::transaction(function () use ($request, $id) {
            $order = Order::whereHas('items.variant.book', function ($query) {
                $query->where('vendor_id', Auth::id());
            })->findOrFail($id);

            $oldStatus = $order->status;
            $newStatus = $request->status;
            if ($order->status === 'delivered') {
                return response()->json(['message' => 'Delivered orders cannot be modified.'], 422);
            }
            // SCENARIO A: Moving TO Cancelled (Restore Stock)
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                foreach ($order->items as $item) {
                    if ($item->type === 'physical') {
                        $item->variant()->lockForUpdate()->increment('stock_quantity', 1);
                    }
                }
            }

            // SCENARIO B: Moving FROM Cancelled back to Active (Deduct Stock)
            if ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
                foreach ($order->items as $item) {
                    if ($item->type === 'physical') {
                        $variant = $item->variant()->lockForUpdate()->first();
                        
                        if ($variant->stock_quantity < 1) {
                            throw new \Exception("Cannot un-cancel: Item '{$item->book->title}' is now out of stock.");
                        }
                        
                        $variant->decrement('stock_quantity', 1);
                    }
                }
            }

            // Update the status
            $order->update(['status' => $newStatus]);

            return response()->json([
                'message' => "Order status updated to " . ucfirst($newStatus),
                'order' => new OrderResource($order->load(['items.variant.book', 'user'])) 
            ]);
        });
    }

public function getPopularBooks(Request $request) // Inject the request
{
    $vendorId = Auth::id();
    
    // Explicitly get the page number from the request
    $page = $request->input('page', 1);
   // \Log::info("Controller received page: " . $request->input('page'));

    $paginatedBooks = Book::where('vendor_id', $vendorId)
        ->with(['variants'])
        ->withCount('orderItems as total_sales')
        ->orderBy('total_sales', 'desc')
        // Pass the page to the paginate method
        ->paginate(10, ['*'], 'page', $page); 

    $paginatedBooks->getCollection()->transform(function ($book) {
        return [
            'title' => $book->title,
            'author' => $book->author_name,
            'cover_image' => $book->cover_image ?: asset('storage/images/d7.jpg'),
            'starting_price' => $book->variants->min('price') ?? 0,
            'formats' => $book->variants->map(fn($v) => ['type' => $v->type]),
            'sales_count' => (int) $book->total_sales,
        ];
    });

    return response()->json($paginatedBooks);
}
        
}