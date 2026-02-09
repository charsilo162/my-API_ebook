<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Http\Resources\LibraryResource;
use App\Models\UserLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserLibraryController extends Controller
{
    /**
     * Display the user's purchased books.
     */
    public function index(Request $request)
            {
                $user = Auth::user();
                
                $query = UserLibrary::with(['book', 'variant'])
                    ->where('user_id', $user->id);

                // Search within my library
                            if ($search = $request->query('search')) {
                                $query->whereHas('book', function ($q) use ($search) {
                                    $q->where('title', 'like', "%{$search}%")
                                    ->orWhere('author_name', 'like', "%{$search}%");
                                });
                            }

                $libraryItems = $query->latest()->paginate(12);
                $libraryItems = $query->latest()->paginate($request->query('per_page', 12));

                // Use your LibraryResource here
                return LibraryResource::collection($libraryItems);
}

    /**
     * Get a secure download link for a purchased digital book.
     */
    public function download(UserLibrary $libraryItem)
        {
            if ($libraryItem->user_id !== Auth::id()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $variant = $libraryItem->variant;
            
            // Cloudinary magic: replace '/upload/' with '/upload/fl_attachment/' 
            // to force the browser to download the file.
            $secureUrl = str_replace('/upload/', '/upload/fl_attachment/', $variant->file_path);

            return response()->json([
                'download_url' => $secureUrl,
                'title' => $libraryItem->book->title
            ]);
        }
}