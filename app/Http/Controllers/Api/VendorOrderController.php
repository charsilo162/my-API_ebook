<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Resources\OrderResource;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

            // 1. Find the order ensuring it belongs to this vendor
            $order = Order::whereHas('items.book', function ($query) {
                $query->where('vendor_id', Auth::id());
            })->findOrFail($id);

            // 2. Handle Stock Restoration if the order is being CANCELLED
            // We only do this if the order wasn't already cancelled (to avoid double-adding stock)
            if ($request->status === 'cancelled' && $order->status !== 'cancelled') {
                foreach ($order->items as $item) {
                    // Check if it's a physical book (Digital books usually don't need stock limits)
                    if ($item->type === 'physical') {
                        $item->variant->increment('stock_quantity', 1);
                    }
                }
            }

            // 3. Update the status
            $order->update([
                'status' => $request->status
            ]);

            return response()->json([
                'message' => "Order status updated to " . ucfirst($request->status),
                'order' => new OrderResource($order->load('items.variant', 'user')) 
            ]);
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