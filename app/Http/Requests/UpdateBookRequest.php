<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'category_id' => 'sometimes|required|exists:categories,id',
            'title'       => 'sometimes|required|string|max:255',
            'author_name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            
            'variants'    => 'sometimes|array|min:1',
            'variants.*.id'             => 'nullable|exists:book_variants,id',
            'variants.*.type'           => 'required|in:digital,physical',
            'variants.*.price'          => 'required|numeric|min:0',
            'variants.*.discount_price' => 'nullable|numeric|lt:variants.*.price',
            'variants.*.stock'          => 'nullable|integer',
            'variants.*.file'           => 'nullable|file|mimes:pdf,epub|max:10000',
        ];
    }
}