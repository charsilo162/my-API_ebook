<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookVariantController extends Controller
{

 
    public function updateStock(Request $request, $variantId)
            {
                $request->validate([
                    'stock_quantity' => 'required|integer|min:0'
                ]);

                // Ensure the variant belongs to a book owned by this vendor
                $variant = \App\Models\BookVariant::whereHas('book', function ($q) {
                    $q->where('vendor_id', Auth::id());
                })->findOrFail($variantId);

                $variant->update([
                    'stock_quantity' => $request->stock_quantity
                ]);

                return response()->json([
                    'message' => 'Stock updated successfully!',
                    'new_stock' => $variant->stock_quantity
                ]);
            }

}