<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function index(Request $request)
    {
        $query = Category::query();

        // Count books in each category (E-book requirement)
        if ($request->has('with_count')) {
            $query->withCount('books');
        }

        // Search by name
        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Sorting
        if ($orderBy = $request->query('order_by')) {
            [$field, $direction] = explode(',', $orderBy);
            $query->orderBy($field, $direction ?? 'asc');
        }

        // Limit for homepage "Top Categories"
        if ($limit = $request->query('limit')) {
            return CategoryResource::collection($query->limit($limit)->get());
        }

        return CategoryResource::collection($query->paginate($request->query('per_page', 15)));
    }

    public function random(Request $request)
    {
        $query = Category::query();
        // Count books in each category (E-book requirement)
        if ($request->has('with_count')) {
            $query->withCount('books');
        }
        // Limit default to 10
        $limit = $request->query('limit', 10);
        // Apply random order
        $query->inRandomOrder();
        
        return CategoryResource::collection($query->limit($limit)->get());
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $payload = [
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
        ];

        if ($request->hasFile('thumbnail')) {
            $payload['thumbnail_url'] = $this->cloudinaryService->uploadFile(
                $request->file('thumbnail'), 
                'categories'
            );
        }

        $category = Category::create($payload);

        return new CategoryResource($category);
    }

    public function show(Category $category)
    {
        // Load books when viewing a specific category
        return new CategoryResource($category->loadCount('books'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:categories,name,' . $category->id,
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if (isset($data['name'])) {
            $category->name = $data['name'];
            $category->slug = Str::slug($data['name']);
        }

        if ($request->hasFile('thumbnail')) {
            if ($category->thumbnail_url) {
                $this->cloudinaryService->deleteFile($category->thumbnail_url);
            }
            $category->thumbnail_url = $this->cloudinaryService->uploadFile(
                $request->file('thumbnail'), 
                'categories'
            );
        }

        $category->save();

        return new CategoryResource($category);
    }

    public function destroy(Category $category)
    {
        // Prevent deletion if books are linked to this category
        if ($category->books()->exists()) {
            return response()->json([
                'message' => "Cannot delete. Category is linked to {$category->books()->count()} books.",
            ], 409);
        }

        if ($category->thumbnail_url) {
            $this->cloudinaryService->deleteFile($category->thumbnail_url);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully.']);
    }



    public function getCategories()
        {
            // 1. Query only 8 random categories that actually have books
            $categories = Category::withCount('books')
                ->whereHas('books') 
                ->inRandomOrder() 
                ->limit(8)        
                ->get()
                ->map(function ($category) {
                    return [
                        'title' => $category->name,
                        'count' => $category->books_count,
                        
                        'image' => $category->image_path 
                            ? asset('storage/' . $category->image_path) 
                            : asset('storage/images/d5.jpg'),
                          'url' => config('app.frontend_url') . '/categories?category=' . $category->uuid,
                        //'url' => url('/categories/' . $category->slug),
                    ];
                });

            return response()->json(['categories' => $categories]);
        }


}