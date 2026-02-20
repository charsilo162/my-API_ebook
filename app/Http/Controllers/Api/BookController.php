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
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

                // 1. Initialize query with Eager Loading
                // We load 'variants' so that ALL variants come with the book
               $query = Book::with(['category', 'variants', 'vendor'])
                    ->withMin('variants', 'price');

                    if (!$request->boolean('include_inactive')) {
                        $query->where('is_active', true);
                    }// Adds 'variants_min_price' attribute automatically

                // 2. Search Logic
                if ($request->filled('search')) {
                
                    $search = $request->query('search');
                    $query->where(function($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                        ->orWhere('author_name', 'like', "%{$search}%");
                    });
                }

                // 3. Category Filter
              if ($request->filled('category_id')) {
                        $query->whereHas('category', function ($q) use ($request) {
                            $q->where('uuid', $request->category_id);
                        });
                    }

                    if ($request->filled('status')) {
                        if ($request->status === 'active') {
                            $query->where('is_active', true);
                        }

                        if ($request->status === 'inactive') {
                            $query->where('is_active', false);
                        }
                    }

                // 4. Professional Sorting
                // Default to latest if no order_by is provided
                if ($request->filled('order_by')) {
                    $sortParams = explode(',', $request->query('order_by'));
                    $field = $sortParams[0];
                    $direction = $sortParams[1] ?? 'asc';
                    $query->orderBy($field, $direction);
                } else {
                    $query->latest();
                }

                // 5. Execution (Limit vs Paginate)
                $perPage = $request->query('per_page', 10);
                
                if ($request->filled('limit')) {
                    $books = $query->limit($request->query('limit'))->get();
                } else {
                    $books = $query->paginate($perPage);
                }


                return BookResource::collection($books);
            }
    public function store(StoreBookRequest $request)
        {
            $data = $request->validated();
           
           // Check if any variant is 'physical' to enforce the bookshop rule
            $hasPhysicalVariant = collect($data['variants'])->contains('type', 'physical');

            if ($hasPhysicalVariant) {
                $hasShop = Auth::user()->vendorProfile->bookshops()->exists();
                if (!$hasShop) {
                    return response()->json([
                        'message' => 'You must register a physical bookshop location before listing physical books.'
                    ], 422);
                }
            }

            // 1. Get all types being submitted
                $types = collect($data['variants'])->pluck('type');

                // 2. Check if the count of types is the same as the count of UNIQUE types
                if ($types->count() !== $types->unique()->count()) {
                    return response()->json([
                        'message' => 'Validation Error',
                        'errors' => [
                            'variants' => ['A book cannot have duplicate formats. You can only have one Physical and one Digital version.']
                        ]
                    ], 422);
                }
                            
            return DB::transaction(function () use ($request, $data) {
                // 1. Upload Cover Image
                $coverUrl = $this->cloudinaryService->uploadFile($request->file('cover_image'), 'books/covers');

                // 2. Create Book Record
                $book = Book::create([
                    'vendor_id'   => Auth::id(),
                    'category_id' => $data['category_id'],
                    'title'       => $data['title'],
                    'slug'        => Str::slug($data['title']) . '-' . time(), // Added for unique URLs
                    'author_name' => $data['author_name'],
                    'description' => $data['description'],
                    'cover_image' => $coverUrl,
                ]);

                // 3. Create Variants
                foreach ($data['variants'] as $index => $variantData) {
                    $filePath = null;
                    
             if ($variantData['type'] === 'digital' && $request->hasFile("variants.{$index}.file")) {
   
                    $filePath = $this->cloudinaryService->uploadFile(
                        $request->file("variants.{$index}.file"), 
                        'books/files', 
                        'raw' 
                    );
                }

                    $book->variants()->create([
                        'type'           => $variantData['type'],
                        'price'          => $variantData['price'],
                        'discount_price' => $variantData['discount_price'] ?? null,
                        'stock_quantity' => $variantData['type'] === 'digital' ? -1 : ($variantData['stock'] ?? 0),
                        'file_path'      => $filePath,
                        'bookshop_id'    => $variantData['type'] === 'physical' ? ($variantData['bookshop_id'] ?? null) : null,
                    ]);
                }
                return new BookResource($book->load(['variants', 'category', 'vendor']));
                //return new BookResource($book->load('variants'));
            });
        }
   

    public function update(UpdateBookRequest $request, Book $book)
        {
            $data = $request->validated();

            // Prevent duplicate types in the request
            $types = collect($data['variants'])->pluck('type');
            if ($types->count() !== $types->unique()->count()) {
                return response()->json(['message' => 'Duplicate variants detected.'], 422);
            }

            return DB::transaction(function () use ($request, $data, $book) {
                
                // 1. Update Basic Info
                $payload = collect($data)->only(['category_id', 'title', 'author_name', 'description'])->toArray();
                if (isset($data['title']) && $data['title'] !== $book->title) {
                    $payload['slug'] = Str::slug($data['title']) . '-' . time();
                }

                // Handle Cover Image (Normal Image)
                if ($request->hasFile('cover_image')) {
                    if ($book->cover_image) {
                        // Images use default 'image' type
                        $this->cloudinaryService->deleteFile($book->cover_image, 'image');
                    }
                    $payload['cover_image'] = $this->cloudinaryService->uploadFile($request->file('cover_image'), 'books/covers');
                }

                $book->update($payload);

                // 2. Sync Variants
                if (isset($data['variants'])) {
                    $incomingVids = collect($data['variants'])->pluck('id')->filter()->toArray();

                    // A. Delete removed variants
                    $variantsToDelete = $book->variants()->whereNotIn('id', $incomingVids)->get();
                    foreach ($variantsToDelete as $oldV) {
                        if ($oldV->file_path) {
                            // CRITICAL: If it was a digital variant, delete as 'raw'
                            $typeToDelete = ($oldV->type === 'digital') ? 'raw' : 'image';
                            $this->cloudinaryService->deleteFile($oldV->file_path, $typeToDelete);
                        }
                        $oldV->delete();
                    }

                    // B. Update or Create
                    foreach ($data['variants'] as $index => $vData) {
                        $variantPayload = [
                            'type'           => $vData['type'],
                            'price'          => $vData['price'],
                            'discount_price' => $vData['discount_price'] ?? null,
                            'stock_quantity' => $vData['type'] === 'digital' ? -1 : ($vData['stock'] ?? 0),
                            'bookshop_id'    => $vData['type'] === 'physical' ? ($vData['bookshop_id'] ?? null) : null,
                            ];

                        // Handle Digital File Uploads (PDFs)
                        if ($vData['type'] === 'digital' && $request->hasFile("variants.{$index}.file")) {
                            // CRITICAL: Explicitly pass 'raw' for PDFs
                            $variantPayload['file_path'] = $this->cloudinaryService->uploadFile(
                                $request->file("variants.{$index}.file"), 
                                'books/files',
                                'raw' 
                            );
                        }

                        if (isset($vData['id'])) {
                            $existing = $book->variants()->find($vData['id']);
                            
                            // If a new file is uploaded, delete the old one correctly
                            if (isset($variantPayload['file_path']) && $existing->file_path) {
                                // Digital files must be deleted as 'raw'
                                $this->cloudinaryService->deleteFile($existing->file_path, 'raw');
                            }
                            
                            $existing->update($variantPayload);
                        } else {
                            $book->variants()->create($variantPayload);
                        }
                    }
                }

                return new BookResource($book->refresh()->load(['variants', 'category', 'vendor']));
            });
        }

    public function show(Book $book)
    {
        return new BookResource($book->load(['variants', 'category']));
    }

    public function destroy(Book $book)
        {
            // 1. Safety check (Your existing logic)
            if ($book->orderItems()->exists()) {
                return response()->json(['message' => 'Cannot delete. Sold books must be archived.'], 409);
            }

            return DB::transaction(function () use ($book) {
                // 2. Delete Cover Image
                if ($book->cover_image) {
                    $this->cloudinaryService->deleteFile($book->cover_image, 'image');
                }

                // 3. Delete Variant Files
                foreach ($book->variants as $variant) {
                    if ($variant->file_path) {
                        // FIX: If it's digital, use 'raw'. Otherwise, use 'image'.
                        $resourceType = ($variant->type === 'digital') ? 'raw' : 'image';
                        $this->cloudinaryService->deleteFile($variant->file_path, $resourceType);
                    }
                }

                $book->delete();
                return response()->json(['message' => 'Book deleted successfully']);
            });
        }

        public function toggleActive(Book $book)
            {
                $book->update([
                    'is_active' => !$book->is_active
                ]);

                return response()->json([
                    'message' => 'Book status updated successfully',
                    'is_active' => $book->is_active
                ]);
            }

}