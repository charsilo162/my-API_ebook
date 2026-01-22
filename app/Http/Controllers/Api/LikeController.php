<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Like;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
// app/Http/Controllers/Api/LikeController.php
public function show(Request $request)
{
    $request->validate([
        'resource_type' => 'required|string',
        'resource_id'   => 'required|integer',
    ]);

    $upCount = Like::where('likeable_type', $request->resource_type)
                   ->where('likeable_id', $request->resource_id)
                   ->where('type', 'up')
                   ->count();

    $downCount = Like::where('likeable_type', $request->resource_type)
                     ->where('likeable_id', $request->resource_id)
                     ->where('type', 'down')
                     ->count();

    $userVote = auth()->check()
        ? Like::where('user_id', auth()->id())
              ->where('likeable_type', $request->resource_type)
              ->where('likeable_id', $request->resource_id)
              ->value('type')
        : null;

    return response()->json([
        'up_count'     => $upCount,
        'down_count'   => $downCount,
        'user_vote'    => $userVote,
    ]);
}

public function toggle(Request $request)
{
    $request->validate([
        'resource_type' => 'required|string',
        'resource_id'   => 'required|integer',
        'type'          => 'required|in:up,down',
    ]);

    $userId = auth()->id();

    $existing = Like::where('user_id', $userId)
        ->where('likeable_type', $request->resource_type)
        ->where('likeable_id', $request->resource_id)
        ->first();

    if ($existing) {
        if ($existing->type === $request->type) {
            $existing->delete();
            $action = 'removed';
        } else {
            $existing->update(['type' => $request->type]);
            $action = 'updated';
        }
    } else {
        Like::create([
            'user_id' => $userId,
            'likeable_type' => $request->resource_type,
            'likeable_id' => $request->resource_id,
            'type' => $request->type,
        ]);
        $action = 'created';
    }

    return response()->json(['action' => $action]);
}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */

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
