<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
 // app/Http/Controllers/Api/CommentController.php
public function index(Request $request)
{
    $request->validate([
        'resource_type' => 'required|string',
        'resource_id'   => 'required|integer',
    ]);

    $comments = Comment::where('commentable_type', $request->resource_type)
        ->where('commentable_id', $request->resource_id)
        ->with('user:id,name')
        ->latest()
        ->paginate(5);

    return CommentResource::collection($comments);
}

public function store(Request $request)
{
    $request->validate([
        'resource_type' => 'required|string',
        'resource_id'   => 'required|integer',
        'body'          => 'required|string|min:3|max:500',
    ]);

    $comment = Comment::create([
        'user_id'         => auth()->id(),
        'commentable_type'=> $request->resource_type,
        'commentable_id'  => $request->resource_id,
        'body'            => $request->body,
    ]);

    return new CommentResource($comment->load('user'));
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
