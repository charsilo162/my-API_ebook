<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
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

        $query = UserLibrary::with(['book.category', 'variant'])
            ->where('user_id', $user->id);

        // Search within my library
        if ($search = $request->query('search')) {
            $query->whereHas('book', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author_name', 'like', "%{$search}%");
            });
        }

        $libraryItems = $query->latest()->paginate($request->query('per_page', 12));

        // We wrap the BookResource but we can add specific "Library" metadata
        return BookResource::collection($libraryItems->pluck('book'));
    }

    /**
     * Get a secure download link for a purchased digital book.
     */
    public function download(UserLibrary $libraryItem)
    {
        // 1. Check Ownership
        if ($libraryItem->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized access to this file.'], 403);
        }

        // 2. Check if the variant is digital
        $variant = $libraryItem->variant;
        if ($variant->type !== 'digital' || !$variant->file_path) {
            return response()->json(['message' => 'This purchase does not include a digital download.'], 400);
        }

        // 3. Return the Cloudinary URL (or a redirect)
        // In a production app, you might use a signed URL for extra security
        return response()->json([
            'download_url' => $variant->file_path,
            'title' => $libraryItem->book->title,
            'format' => pathinfo($variant->file_path, PATHINFO_EXTENSION)
        ]);
    }
}