<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
// use App\Http\Resources\OrderResource;
use App\Http\Resources\UserOrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
public function index(Request $request)
{
    $query = Auth::user()->orders()
        ->whereHas('items', function ($q) {
            $q->where('type', 'physical');
        });

    if ($request->has('search') && $request->search != '') {
        $query->where('reference', 'LIKE', '%' . $request->search . '%');
    }

    $orders = $query->with(['items.book.vendor', 'items.variant.bookshop'])
        ->latest()
        ->paginate(2);

  

    return UserOrderResource::collection($orders);
}
    public function show($id)
    {
        $order = Auth::user()->orders()
            ->with(['items.book'])
            ->findOrFail($id);

        return new UserOrderResource($order);
    }
}