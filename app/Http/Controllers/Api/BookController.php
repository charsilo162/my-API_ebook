<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function index(Request $request)
    {
        // Load variants and category by default to avoid N+1 issues
        $query = Book::with(['category', 'variants']);

        // Handle with_count (e.g., 'variants')
        if ($withCount = $request->query('with_count')) {
            $query->withCount(explode(',', $withCount));
        }

        // Search by Title or Author
        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author_name', 'like', "%{$search}%");
            });
        }

        // Filter by Category
        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Professional Sorting
        if ($orderBy = $request->query('order_by')) {
            [$field, $direction] = explode(',', $orderBy);
            $query->orderBy($field, $direction ?? 'asc');
        }

        // Pagination or Limit
        if ($limit = $request->query('limit')) {
            return BookResource::collection($query->limit($limit)->get());
        }

        return BookResource::collection($query->paginate($request->query('per_page', 10)));
    }

   public function store(StoreBookRequest $request)
{
    // The data is already validated here
    $data = $request->validated();

        return DB::transaction(function () use ($request, $data) {
            // 1. Upload Cover Image
            $coverUrl = $this->cloudinaryService->uploadFile($request->file('cover_image'), 'books/covers');

            // 2. Create Book Record
            $book = Book::create([
                'vendor_id'   => Auth::id(), // Assuming vendor is logged in
                'category_id' => $data['category_id'],
                'title'       => $data['title'],
                'author_name' => $data['author_name'],
                'description' => $data['description'],
                'cover_image' => $coverUrl,
            ]);

            // 3. Create Variants (Digital and/or Physical)
            foreach ($data['variants'] as $index => $variantData) {
                $filePath = null;
                
                // If digital, upload the book file
                if ($variantData['type'] === 'digital' && $request->hasFile("variants.{$index}.file")) {
                    $filePath = $this->cloudinaryService->uploadFile($request->file("variants.{$index}.file"), 'books/files');
                }

                $book->variants()->create([
                    'type'           => $variantData['type'],
                    'price'          => $variantData['price'],
                    'discount_price' => $variantData['discount_price'] ?? null,
                    'stock_quantity' => $variantData['type'] === 'digital' ? -1 : ($variantData['stock'] ?? 0),
                    'file_path'      => $filePath,
                ]);
            }

            return new BookResource($book->load('variants'));
        });
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
            // The data is already validated here
            $data = $request->validated();
                    return DB::transaction(function () use ($request, $data, $book) {
                        
                        $payload = collect($data)->only(['category_id', 'title', 'author_name', 'description'])->toArray();

                // 1. Handle Cover Image Update
                if ($request->hasFile('cover_image')) {
                    // Delete old one
                    if ($book->cover_image) {
                        $this->cloudinaryService->deleteFile($book->cover_image);
                    }
                    $payload['cover_image'] = $this->cloudinaryService->uploadFile($request->file('cover_image'), 'books/covers');
                }

                $book->update($payload);

                // 2. Handle Variants Update
                if (isset($data['variants'])) {
                    foreach ($data['variants'] as $index => $vData) {
                        
                        $variantPayload = [
                            'type'           => $vData['type'],
                            'price'          => $vData['price'],
                            'discount_price' => $vData['discount_price'] ?? null,
                            'stock_quantity' => $vData['type'] === 'digital' ? -1 : ($vData['stock'] ?? 0),
                        ];

                        // If a new digital file is uploaded
                        if ($vData['type'] === 'digital' && $request->hasFile("variants.{$index}.file")) {
                            $variantPayload['file_path'] = $this->cloudinaryService->uploadFile(
                                $request->file("variants.{$index}.file"), 
                                'books/files'
                            );
                        }

                        // Update existing variant or create a new one
                        if (isset($vData['id'])) {
                            $existingVariant = $book->variants()->find($vData['id']);
                            
                            // Cleanup old file if replacing digital file
                            if (isset($variantPayload['file_path']) && $existingVariant->file_path) {
                                $this->cloudinaryService->deleteFile($existingVariant->file_path);
                            }
                            
                            $existingVariant->update($variantPayload);
                        } else {
                            $book->variants()->create($variantPayload);
                        }
                    }
                }

                return new BookResource($book->load('variants'));
            });
        }


    public function show(Book $book)
    {
        return new BookResource($book->load(['variants', 'category']));
    }

    public function destroy(Book $book)
    {
        // Safety check: Don't delete if users have already purchased it
        if ($book->userLibraries()->exists()) {
             return response()->json(['message' => 'Cannot delete. Users already own this e-book.'], 409);
        }

        // Delete files from Cloudinary
        $this->cloudinaryService->deleteFile($book->cover_image);
        foreach($book->variants as $variant) {
            if($variant->file_path) $this->cloudinaryService->deleteFile($variant->file_path);
        }

        $book->delete();
        return response()->json(['message' => 'Book deleted successfully']);
    }
}