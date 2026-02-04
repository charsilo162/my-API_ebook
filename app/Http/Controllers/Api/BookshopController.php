<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bookshop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookshopController extends Controller
{
    /**
     * Get all bookshops belonging to the authenticated vendor
     */
    public function index()
    {
        $vendor = Auth::user()->vendorProfile;
        
        if (!$vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
        }

        return response()->json($vendor->bookshops);
    }

    /**
     * Add a new physical bookshop location
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'shop_name' => 'required|string|max:255',
            'address'   => 'required|string',
            'city'      => 'required|string',
            'phone'     => 'nullable|string',
        ]);

        $vendor = Auth::user()->vendorProfile;
        $shop = $vendor->bookshops()->create($data);

        return response()->json([
            'message' => 'Bookshop registered successfully',
            'shop' => $shop
        ], 201);
    }

    /**
     * Update shop details
     */
    public function update(Request $request, Bookshop $bookshop)
    {
        // Security check
        if ($bookshop->vendor_id !== Auth::user()->vendorProfile->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'shop_name' => 'sometimes|string|max:255',
            'address'   => 'sometimes|string',
            'city'      => 'sometimes|string',
            'phone'     => 'nullable|string',
        ]);

        $bookshop->update($data);

        return response()->json(['message' => 'Bookshop updated', 'shop' => $bookshop]);
    }

    /**
     * Delete a shop (Check for inventory first)
     */
    public function destroy(Bookshop $bookshop)
    {
        if ($bookshop->vendor_id !== Auth::user()->vendorProfile->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Professional check: Don't delete if books are still mapped to this shop
        if ($bookshop->bookVariants()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete shop. You have books assigned to this location.'
            ], 409);
        }

        $bookshop->delete();
        return response()->json(['message' => 'Shop deleted successfully']);
    }
}