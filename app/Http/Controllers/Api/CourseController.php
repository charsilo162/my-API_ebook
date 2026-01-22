<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseEnrollmentResource;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    protected $cloudinaryService;


    public function __construct(CloudinaryService $cloudinaryService)
        {
            $this->cloudinaryService = $cloudinaryService;
        }
    public function index(Request $request)
    {
        $query = Course::query();

        // 1. Only published courses
        if (! $request->boolean('include_unpublished')) {
            $query->where('publish', true);
        }

        // 2. Category
        if ($categorySlug = $request->query('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $categorySlug));
        }

        // 3. Tutor
        if ($tutorId = $request->query('tutor')) {
            $query->where('assigned_tutor_id', $tutorId);
        }

        // 4. Type
        if ($type = $request->query('type') ?? $request->query('filterType')) {
            $query->where('type', $type);
        }

        // 5. Price range
        if ($priceRange = $request->query('price')) {
            [$min, $max] = explode('-', $priceRange);
            $min = (int) $min;
            $max = (int) $max;
            $query->whereHas('currentPrice', function ($q) use ($min, $max) {
                $q->where('amount', '>=', $min);
                if ($max > 0) $q->where('amount', '<=', $max);
            });
        }

        // 6. Unified Search: Title + Location (from ?q= or ?search= or ?location=)
        $searchTerm = $request->query('q') 
            ?? $request->query('search') 
            ?? $request->query('location');

        if ($searchTerm && trim($searchTerm) !== '') {
            $searchTerm = trim($searchTerm);

            // Search in course title
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                ->orWhere('description', 'like', "%{$searchTerm}%"); // optional bonus
            });

            // Also search in center address
            $query->orWhereHas('centers', function ($q) use ($searchTerm) {
                $q->where('address', 'like', "%{$searchTerm}%")
                ->orWhere('name', 'like', "%{$searchTerm}%");
            });
        }

        // 7. Center ID
        if ($centerId = $request->query('center_id')) {
            $query->whereHas('centers', fn($q) => $q->where('id', $centerId));
        }

        // 8. Uploader
        if ($uploaderId = $request->query('uploader')) {
            $query->where('uploader_user_id', $uploaderId);
        }

        // 9. Random
        if ($request->boolean('random')) {
            $query->inRandomOrder();
        }

        // Eager loads
        $query->with([
            'currentPrice', 'centers', 'category',
            'videos' => fn($q) => $q->orderByPivot('order_index')->withPivot('order_index')->limit(1)
        ])->withCount([
            'users as registered_count',
            'comments as comments_count',
            'likes as likes_count' => fn($q) => $q->where('type', 'up'),
            'likes as dislikes_count' => fn($q) => $q->where('type', 'down'),
        ]);

        // Pagination
        if ($request->boolean('paginate')) {
            $courses = $query->paginate($request->query('per_page', 12));
        } else {
            $courses = $query->limit($request->query('limit', 6))->get();
        }

        return CourseResource::collection($courses);
    }
  
    public function edit($id)  // ← Remove model binding!
    {
        // dd($id);
        $course = Course::with(['centers', 'category', 'currentPrice'])
                        ->findOrFail($id);  // ← Direct ID lookup, no slug nonsense

        return new CourseResource($course);
    }


public function show( Course $course)
{
    // Log the incoming request data
    

    if (! $course->publish && (! auth()->check() || auth()->id() !== $course->uploader_user_id)) {
        abort(404);
    }

    $course->load([
        'centers',
        'category',
        'currentPrice',
        'videos' => fn($q) => $q->orderByPivot('order_index')
                          ->withPivot('order_index'),
    ])

    ->loadCount([
        'users as registered_count',
        'comments as comments_count',
        'likes as likes_count' => fn($q) => $q->where('type', 'up'),
        'likes as dislikes_count' => fn($q) => $q->where('type', 'down'),
        // 'views as views_count',
    ])

    ->addSelect([
        'average_rating' => \App\Models\Comment::selectRaw('COALESCE(AVG(rating), 4.34)')
            ->whereColumn('course_id', 'courses.id')
            ->limit(1)
    ]);
    Log::info('Course full object:', $course->toArray());
    return new CourseResource($course);
} /**
 * Update course using raw ID — completely bypasses slug binding
 */
   public function store(Request $request)
        {
            $validated = $request->validate([
                'category_id'  => 'required|integer|exists:categories,id',
                'title'        => 'required|string|max:100|unique:courses,title',
                'description'  => 'required|string',
                'type'         => 'required|in:physical,online',
                'center_id'    => $request->type === 'physical' ? 'required|integer|exists:centers,id' : 'nullable',
                'image_thumb'  => 'nullable|image|max:5120',
                'publish'      => 'boolean',
                'price_amount' => 'required|numeric|min:0',
            ]);

            $data = $validated;
            $data['uploader_user_id'] = auth()->id();
            $data['publish'] = $validated['publish'] ?? false;
            $data['slug'] = \Str::slug($validated['title']);

            if ($request->hasFile('image_thumb')) {
                // Upload to Cloudinary
                $data['image_thumbnail_url'] = $this->cloudinaryService->uploadFile(
                    $request->file('image_thumb'), 
                    'courses'
                );
            }

            $course = Course::create($data);

            if ($request->filled('price_amount')) {
                $course->price()->create(['amount' => $request->price_amount]);
            }

            if ($request->type === 'physical' && $request->center_id) {
                $course->centers()->attach($request->center_id);
            }

            return new CourseResource($course->fresh(['centers', 'price']));
        }
    public function update(Request $request, $id)
        {
            $course = Course::findOrFail($id);
            
            $validated = $request->validate([
                'category_id' => 'sometimes|required|integer|exists:categories,id',
                'title'       => 'sometimes|required|string|max:100|unique:courses,title,' . $course->id,
                'description' => 'sometimes|required|string',
                'type'        => 'sometimes|required|in:physical,online',
                'center_id'   => 'nullable|integer|exists:centers,id',
                'image_thumb' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'publish'     => 'sometimes|boolean',
                'price_amount' => 'sometimes|numeric|min:0',
            ]);

            $data = $validated;

            if (isset($data['title'])) {
                $data['slug'] = \Str::slug($data['title']);
            }

            // Handle Cloudinary Image Swap
            if ($request->hasFile('image_thumb')) {
                // 1. Delete old Cloudinary image
                if ($course->image_thumbnail_url) {
                    $this->cloudinaryService->deleteFile($course->image_thumbnail_url);
                }

                // 2. Upload new image
                $data['image_thumbnail_url'] = $this->cloudinaryService->uploadFile(
                    $request->file('image_thumb'), 
                    'courses'
                );
            }

            unset($data['price_amount']);
            $course->update($data);

            if ($request->filled('price_amount')) {
                $course->price()->updateOrCreate(
                    ['course_id' => $course->id],
                    ['amount' => $request->price_amount]
                );
            }

            if ($request->filled('type')) {
                if ($request->type === 'physical' && $request->filled('center_id')) {
                    $course->centers()->sync([$request->center_id]);
                } elseif ($request->type === 'online') {
                    $course->centers()->detach();
                }
            }

            return new CourseResource($course->fresh(['centers', 'category', 'currentPrice']));
        }

    public function destroy(Course $course)
        {
            // Delete image from Cloudinary
            if ($course->image_thumbnail_url) {
                $this->cloudinaryService->deleteFile($course->image_thumbnail_url);
            }

            $course->centers()->detach();
            $course->delete();

            return response()->json([
                'message' => 'Course and associated cloud images deleted successfully'
            ]);
        }
    public function togglePublish(Course $course)
{
    // Security: Only uploader can toggle
    // if ($course->uploader_user_id !== auth()->id()) {
    //     return response()->json(['error' => 'Unauthorized'], 403);
    // }

    $course->update(['publish' => !$course->publish]);

    return new CourseResource($course->loadMissing(['currentPrice', 'centers']));
}

// app/Http/Controllers/Api/CourseController.php
public function watch(Course $course)
{
  
    // Authorization: only enrolled users
    // if (!auth()->check() || !$course->users()->where('user_id', auth()->id())->exists()) {
    //     abort(403, 'You are not enrolled in this course.');
    // }
    //  Log::info('User logged in', [
    //         'user_id' =>$course,
           
    //         ]);
    $course->load([
        'videos' => fn($q) => $q->orderByPivot('order_index')->withPivot('order_index')
    ]);
//  Log::info('User ', [
//             'user' =>$course,
           
//             ]);
    return new CourseResource($course);
}

// app/Http/Controllers/Api/CourseController.php

public function noVideos(Request $request)
{
    $query = Course::query()
        ->where(function ($q) {
            $q->where('uploader_user_id', auth()->id())
              ->orWhere('assigned_tutor_id', auth()->id());
        })
        ->with(['category', 'centers'])   // ← THIS LINE MUST BE HERE
        ->withCount('videos')
        ->doesntHave('videos');
    if ($request->filled('search')) {
     $query->where('title', 'like', '%' . $request->search . '%');
 }

 $courses = $query->latest()->paginate(12);

 return CourseResource::collection($courses);
}



public function publish(Course $course)
{
    if (!in_array($course->uploader_user_id, [auth()->id(), auth()->user()?->tutor?->id ?? null])) {
        abort(403);
    }

    $course->update(['publish' => true]);

    return new CourseResource($course);
}



public function myCourseEnrollments(Request $request)
{
    $user = $request->user();
log::info('MY COURSE ENROLLMENTS REQUEST', [
    'user_id' => $user,
    'request' => $request->all(),
]);
    $courses = Course::query()
        ->where('uploader_user_id', $user->id)

        // Filter by course
        ->when($request->filled('course_id'), function ($q) use ($request) {
            $q->where('id', $request->course_id);
        })

        // Filter by category
        ->when($request->filled('category_id'), function ($q) use ($request) {
            $q->where('category_id', $request->category_id);
        })

        ->with([
            'category',

            'students' => function ($q) use ($request) {

                // Search student
                if ($request->filled('search')) {
                    $q->where(function ($sub) use ($request) {
                        $sub->where('users.name', 'like', "%{$request->search}%")
                            ->orWhere('users.email', 'like', "%{$request->search}%");
                    });
                }

                // Min amount
                if ($request->filled('min_amount')) {
                    $q->wherePivot('paid_amount', '>=', $request->min_amount);
                }

                // Date range
                if ($request->filled('from_date')) {
                    $q->wherePivot('paid_at', '>=', $request->from_date);
                }

                if ($request->filled('to_date')) {
                    $q->wherePivot('paid_at', '<=', $request->to_date);
                }

                $q->select('users.id', 'users.name', 'users.email')
                  ->withPivot(['payment_reference', 'paid_amount', 'paid_at']);
            }
        ])
        ->paginate(10);

    return CourseEnrollmentResource::collection($courses);
}


}
