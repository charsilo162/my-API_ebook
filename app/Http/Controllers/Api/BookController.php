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
                            ->withMin('variants', 'price'); // Adds 'variants_min_price' attribute automatically

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
                return new BookResource($book->load(['variants', 'category', 'vendor']));
                //return new BookResource($book->load('variants'));
            });
        }
   

   public function update(UpdateBookRequest $request, Book $book)
        {
            $data = $request->validated();

            // Prevent duplicate types in the request before even touching the DB
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

                // Handle Cover Image
                if ($request->hasFile('cover_image')) {
                    if ($book->cover_image) {
                        $this->cloudinaryService->deleteFile($book->cover_image);
                    }
                    $payload['cover_image'] = $this->cloudinaryService->uploadFile($request->file('cover_image'), 'books/covers');
                }

                $book->update($payload);

                // 2. Sync Variants (The Safe Way)
                if (isset($data['variants'])) {
                    $incomingVids = collect($data['variants'])->pluck('id')->filter()->toArray();

                    // A. Delete variants that are no longer in the UI
                    // Important: Do this first to free up 'type' slots in the DB
                    $variantsToDelete = $book->variants()->whereNotIn('id', $incomingVids)->get();
                    foreach ($variantsToDelete as $oldV) {
                        if ($oldV->file_path) {
                            $this->cloudinaryService->deleteFile($oldV->file_path);
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
                        ];

                        // Handle Digital File Uploads
                        if ($vData['type'] === 'digital' && $request->hasFile("variants.{$index}.file")) {
                            $variantPayload['file_path'] = $this->cloudinaryService->uploadFile(
                                $request->file("variants.{$index}.file"), 
                                'books/files'
                            );
                        }

                        if (isset($vData['id'])) {
                            // Updating existing variant
                            $existing = $book->variants()->find($vData['id']);
                            
                            // Delete old file if a new one is uploaded
                            if (isset($variantPayload['file_path']) && $existing->file_path) {
                                $this->cloudinaryService->deleteFile($existing->file_path);
                            }
                            
                            $existing->update($variantPayload);
                        } else {
                            // Creating a brand new variant row
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