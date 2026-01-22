<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
class AuthController extends Controller
{
    protected $cloudinaryService;
    public function __construct(CloudinaryService $cloudinaryService)
        {
            $this->cloudinaryService = $cloudinaryService;
        }
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'type' => 'required|in:user,center,tutor',
            'password' => 'required|min:6|confirmed',
            'photo' => 'nullable|image|max:2048', 
        ]);

        $userData = [
            'name' => $data['name'],
            'type' => $data['type'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ];
        
        if ($request->hasFile('photo')) {
            // Upload to Cloudinary using our reusable service
            $userData['photo_path'] = $this->cloudinaryService->uploadFile(
                $request->file('photo'), 
                'profile_photos'
            ); 
        }

        $user = User::create($userData);
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 200);
    }
   public function updateProfile(Request $request)
        {
            $user = $request->user();
//  Log::info('Incoming user request', [
//     'data'  => $request->all(),
//     'files' => $request->file(),
// ]);

            $data = $request->validate([
                'name'     => 'sometimes|string|max:255',
                'email'    => 'sometimes|email|unique:users,email,' . $user->id,
                'password' => 'sometimes|min:6|confirmed',
                'photo'    => 'sometimes|nullable|image|max:2048',
            ]);

            if ($request->has('password')) {
                $data['password'] = Hash::make($data['password']);
            }

            if ($request->hasFile('photo')) {
                // 1. Delete old photo from Cloudinary if it exists
                if ($user->photo_path) {
                    $this->cloudinaryService->deleteFile($user->photo_path);
                }

                // 2. Upload new photo
                $data['photo_path'] = $this->cloudinaryService->uploadFile(
                    $request->file('photo'), 
                    'profile_photos'
                );
            }

            // Remove the 'photo' file object from the data array so it doesn't interfere with update
            unset($data['photo']);
            
            $user->update($data);

            return response()->json([
                'user' => new UserResource($user->fresh()),
            ], 200);
        }
        public function login(Request $request)
        {
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if (!Auth::attempt($credentials)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }

            $user = Auth::user();
            $token = $user->createToken('spa')->plainTextToken;
                Log::info('User logged in', [
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => $token,
            ]);
            return response()->json([
                'message' => 'Logged in successfully',
                'token' => $token,
                'user' => new UserResource($user),
            ]);

            
        }
    public function logout()
    {
        // \Log::alert('API LOGOUT HIT — USER ID: ' . auth()->id());
        // \Log::info('Tokens before delete:', ['count' => auth()->user()->tokens()->count()]);

        auth()->user()->tokens()->delete();

    

        return response()->json(['message' => 'Logged out successfully']);
    }

        public function enrolledCourses(Request $request)
    {
        $user = $request->user();
        $query = $user->enrolledCourses();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                ->orWhere('description', 'like', "%$search%");
            });
        }

        $courses = $query->with('videos')->paginate(9);

        return CourseResource::collection($courses);
    }
}