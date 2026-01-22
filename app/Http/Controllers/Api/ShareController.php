<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Share;
use Illuminate\Http\Request;

class ShareController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function count(Request $request)
{
    $request->validate([
        'resource_type' => 'required|string',
        'resource_id'   => 'required|integer',
    ]);

    $count = Share::where('shareable_type', $request->resource_type)
                  ->where('shareable_id', $request->resource_id)
                  ->count();

    $userShared = auth()->check()
        ? Share::where('user_id', auth()->id())
               ->where('shareable_type', $request->resource_type)
               ->where('shareable_id', $request->resource_id)
               ->exists()
        : false;

    return response()->json([
        'count' => $count,
        'user_shared' => $userShared,
    ]);
}

public function store(Request $request)
{
    // NUCLEAR DEBUG — THIS WILL TELL YOU EVERYTHING
    // \Log::info('SHARE ENDPOINT HIT', [
    //     'ip'            => $request->ip(),
    //     'user_agent'    => $request->userAgent(),
    //     'headers'       => $request->headers->all(),
    //     'bearer_token'  => $request->bearerToken(),
    //     'input'         => $request->all(),
    //     'auth_check'    => auth('sanctum')->check(),
    //     'auth_user'     => auth('sanctum')->user()?->only(['id', 'name', 'email']),
    //     'auth_id'       => auth('sanctum')->id(),
    // ]);

    $request->validate([
        'resource_type' => 'required|string',
        'resource_id'   => 'required|integer',
        'platform'      => 'required|string|in:facebook,twitter,linkedin,whatsapp,copy',
    ]);

    $userId = auth('sanctum')->id();

    if (!$userId) {
        \Log::warning('SHARE FAILED: NO AUTHENTICATED USER', [
            'bearer_token_present' => !empty($request->bearerToken()),
            'token_valid' => $request->bearerToken() ? 'checking...' : 'missing',
        ]);

        return response()->json([
            'error' => 'Unauthenticated',
            'debug' => 'No user found from Bearer token',
        ], 401);
    }

    // SUCCESS — USER IS AUTHENTICATED
    \Log::info('SHARE SUCCESS — CREATING RECORD', [
        'user_id' => $userId,
        'platform' => $request->platform,
        'resource' => $request->resource_type . ' #' . $request->resource_id,
    ]);

    Share::create([
        'user_id'        => $userId,
        'platform'       => $request->platform,
        'shareable_type' => $request->resource_type,
        'shareable_id'   => $request->resource_id,
    ]);

    return response()->json([
        'message' => 'Shared successfully!',
        'user_id' => $userId,
        'platform' => $request->platform,
    ], 200);
}


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
