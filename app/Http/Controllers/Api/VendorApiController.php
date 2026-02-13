<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\Bookshop;
use Illuminate\Http\Request;
use App\Http\Requests\StoreBookshopRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VendorApiController extends Controller
{
    /**
     * Get the authenticated vendor's profile and stats
     */
    public function profile()
    {
       // $vendor = Auth::user()->vendorProfile;
         $vendor = Auth::user()->vendorProfile->load('user');
        if (!$vendor) {
            return response()->json(['message' => 'User is not a registered vendor'], 404);
        }

        // Load stats for the dashboard
        return response()->json([
            'store_name' => $vendor->store_name,
            'bio'        => $vendor->bio,
            'balance'    => $vendor->balance,
            'user'        => $vendor->user,
            'total_books' => $vendor->books()->count(),
            'total_sales' => $vendor->books()->withCount('variants')->get()->sum('variants_count'),
        ]);
    }

    /**
     * Register as a Vendor
     */
    public function registerVendor(Request $request)
    {
        $data = $request->validate([
            'store_name' => 'required|string|unique:vendors,store_name',
            'bio'        => 'nullable|string',
        ]);

        $vendor = Vendor::firstOrCreate(
            ['user_id' => Auth::user()->id],
            $data
        );

        return response()->json(['message' => 'Vendor profile created successfully', 'vendor' => $vendor]);
    }
        /**
     * Update the authenticated vendor's profile
     */
    public function updateProfile(Request $request)
        {
            $user = Auth::user();
            
            $vendor = $user->vendorProfile;

            $data = $request->validate([
                // User fields
                'first_name' => 'required|string|max:255',
                'last_name'  => 'required|string|max:255',
                'email'      => 'required|email|unique:users,email,' . $user->id,
                'phone'      => 'nullable|string|max:20',
                
                // Vendor fields
                'store_name' => 'required|string|unique:vendors,store_name,' . $vendor->id,
                'bio'        => 'nullable|string',
            ]);

            return DB::transaction(function () use ($user, $vendor, $data) {
                // Update User table
                $user->update([
                    'first_name' => $data['first_name'],
                    'last_name'  => $data['last_name'],
                    'name'       => $data['first_name'] . ' ' . $data['last_name'], // Sync full name
                    'email'      => $data['email'],
                    'phone'      => $data['phone'],
                ]);

                // Update Vendor table
                $vendor->update([
                    'store_name' => $data['store_name'],
                    'bio'        => $data['bio'],
                ]);

                return response()->json([
                    'message' => 'Full profile updated successfully',
                    'user' => $user->load('vendorProfile')
                ]);
            });
        }
    /**
     * List all bookshops for the vendor
     */
 public function listShops()
{
    // Correct way: Access the vendor profile first
    $vendor = Auth::user()->vendorProfile;
    
    // This will now correctly use 'vendor_id' to find the shops
    return response()->json($vendor->bookshops);
}

    /**
     * Add a physical shop location
     */
    public function addShop(StoreBookshopRequest $request)
    {
        $vendor = Auth::user()->vendorProfile;

        $shop = $vendor->bookshops()->create($request->validated());

        return response()->json([
            'message' => 'Physical bookshop registered successfully',
            'shop' => $shop
        ]);
    } 

    public function update(Request $request, Bookshop $shop)
        {
            // Ensure the shop belongs to the authenticated vendor
            if ($shop->vendor_id !== Auth::user()->vendorProfile->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $data = $request->validate([
                'shop_name' => 'required|string|max:255',
                'address'   => 'required|string',
                'city'      => 'required|string',
                'state'     => 'required|string',
                'phone'     => 'required|string',
            ]);

            $shop->update($data);

            return response()->json(['message' => 'Shop updated successfully', 'shop' => $shop]);
        }

    /**
     * Delete a shop location
     */
    public function deleteShop(Bookshop $bookshop)
    {
        // Ensure the vendor owns this shop
        if ($bookshop->vendor_id !== Auth::user()->vendorProfile->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if there are physical books linked to this shop
        if ($bookshop->bookVariants()->exists()) {
            return response()->json(['message' => 'Cannot delete shop. Move inventory to another branch first.'], 409);
        }

        $bookshop->delete();
        return response()->json(['message' => 'Shop location removed']);
    }
}