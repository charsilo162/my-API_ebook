<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Services\CloudinaryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|string|email|unique:users',
            'password'   => 'required|string|min:8',
            'role'       => 'required|in:user,vendor',
            // Optional Vendor Fields
            'store_name' => 'required_if:role,vendor|nullable|string|unique:vendors,store_name',
            'bio'        => 'nullable|string',
            'photo'      => 'nullable|image|max:2048'
        ]);
            log::info('Registering new user', [
                        'email' => $request->email,
                        'role'  => $request->role,
                        'request_all' => $request->all(),
                    ]);
        return DB::transaction(function () use ($request) {
            
            // 1. Upload Photo if present
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $this->cloudinary->uploadFile($request->file('photo'), 'profiles');
            }

            // 2. Create Core User
           $user = User::create([
                    'name'       => $request->first_name . ' ' . $request->last_name, // Combine them here
                    'first_name' => $request->first_name,
                    'last_name'  => $request->last_name,
                    'email'      => $request->email,
                    'phone'      => $request->phone,
                    'password'   => Hash::make($request->password),
                    'photo_path' => $photoPath,
                    'type'       => $request->role, 
                ]);

            // 3. Create Vendor Profile if role is vendor
            if ($request->role === 'vendor') {
                $user->vendorProfile()->create([
                    'store_name' => $request->store_name,
                    'bio'        => $request->bio,
                    'balance'    => 0.00
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'user'         => $user->load('vendorProfile')
            ], 201);
        });
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

        Auth::user()->tokens()->delete();

    

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