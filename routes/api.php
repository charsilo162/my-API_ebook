<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookshopController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CenterController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ShareController;
use App\Http\Controllers\Api\ShowcaseController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\UserLibraryController;
use App\Http\Controllers\Api\VendorApiController;
use App\Http\Controllers\Api\VendorOrderController;


    // ====================================
    // PUBLIC ROUTES (NO LOGIN REQUIRED)
    // ====================================
        Route::get('/vendor/showcase', [ShowcaseController::class, 'getShowcase']);
            Route::get('/public/categories', [CategoryController::class, 'getCategories']);
    Route::get('/test', fn() => response()->json(['message' => 'API IS WORKING!']));
Route::middleware('auth:sanctum')->get('/stats', [StatsController::class, 'index']);
    // Categories & Centers (public)
    Route::get('/categories', [CategoryController::class, 'index']);

    // Route::get('/books', [BookController::class, 'index']);
    Route::resource('books', BookController::class)->only(['index', 'show']);
    Route::get('/books/{book:uuid}', [BookController::class, 'show']);
    //  Route::get('/books/{uuid}', [BookController::class, 'show']);
    Route::get('/categories/count', [CategoryController::class, 'count']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']); // GET /api/categories/{id} (single)


        // Courses (public index + show)
        Route::get('courses/without-videos', [CourseController::class, 'noVideos']);
        Route::get('courses/count', [CategoryController::class, 'count']);
        Route::apiResource('courses', CourseController::class)->only(['index', 'show']);

        // COMMENTS: READ = PUBLIC, WRITE = PROTECTED
        Route::get('comments', [CommentController::class, 'index']);        // ← Public: everyone sees
        Route::get('likes', [LikeController::class, 'show']);               // ← Public
        Route::get('shares/count', [ShareController::class, 'count']);      // ← Public

        // Auth (public)
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);

        // Route::post('/payment/webhook', [PaymentController::class, 'handleWebhook']);
        // Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
        // // ====================================
        // PROTECTED ROUTES (REQUIRES LOGIN)
        // ====================================
    Route::middleware('auth:sanctum')->group(function () {
    Route::post('categories', [CategoryController::class, 'store']);
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);



Route::middleware('auth:sanctum')->group(function () {
    Route::delete('books/{book}', [BookController::class, 'destroy']);
});

Route::prefix('vendor')->group(function () {
        Route::get('/profile', [VendorApiController::class, 'profile']);
        Route::post('/update-profile', [VendorApiController::class, 'updateProfile']); // New
        Route::get('/shops', [VendorApiController::class, 'listShops']);
        Route::post('/shops', [VendorApiController::class, 'addShop']);
        Route::delete('/shops/{bookshop}', [VendorApiController::class, 'deleteShop']);
    });
    Route::post('/vendor/register', [VendorApiController::class, 'registerVendor']);




Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/me/enrolled-courses', [AuthController::class, 'enrolledCourses']);
Route::put('/me/profile', [AuthController::class, 'updateProfile']);
    // Route::post('/me/profile', [AuthController::class, 'updateProfile']);
Route::apiResource('bookshops', BookshopController::class);


    Route::middleware('auth:sanctum')->post('/payment/initialize', [PaymentController::class, 'initialize']);
    // ONLY LOGGED-IN USERS CAN POST COMMENTS
    Route::post('comments', [CommentController::class, 'store']);     // ← PROTECTED
    Route::post('/books', [BookController::class, 'store']);
   
    // Likes & Shares (require login)
    Route::post('likes/toggle', [LikeController::class, 'toggle']);
    Route::post('shares', [ShareController::class, 'store']);
    Route::get('/my-library', [UserLibraryController::class, 'index']);
    Route::get('/my-library/{libraryItem}/download', [UserLibraryController::class, 'download']);
});





Route::middleware('auth:sanctum')->group(function () {
    
    // Vendor Specific Routes
    Route::prefix('vendor')->group(function () {
        Route::get('/orders', [VendorOrderController::class, 'index']);
        Route::get('/popular-books', [VendorOrderController::class, 'getPopularBooks']);
        Route::patch('/orders/{id}/status', [VendorOrderController::class, 'updateStatus']);
    });

    // Customer Specific Routes
    Route::get('/my-orders/{id}', [VendorOrderController::class, 'show']); // Reuse logic for single order
});


Route::middleware('auth:sanctum')->group(function () {
    // Library Endpoints
    Route::get('/library', [UserLibraryController::class, 'index']);
    Route::get('/library/{libraryItem}/download', [UserLibraryController::class, 'download']);
    // Route::get('/stats', [StatsController::class, 'index']);
    // Payment Endpoints
    Route::post('/payments/initialize', [PaymentApiController::class, 'initialize']);
});

// The Callback is usually a GET request from Paystack
Route::get('/payments/callback', [PaymentApiController::class, 'callback'])->name('payment.callback');