<?php

namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Ensure you handle actual authorization logic later
    }

   public function rules()
        {
            return [
                'category_id' => 'required|exists:categories,id',
                'title'       => 'required|string|max:255',
                'author_name' => 'required|string|max:255',
                'description' => 'required|string',
                'cover_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
                
                'variants'                  => 'required|array|min:1',
                'variants.*.type'           => 'required|in:digital,physical',
                'variants.*.price'          => 'required|numeric|min:0',
                'variants.*.discount_price' => 'nullable|numeric|lt:variants.*.price',
                'variants.*.stock'          => 'nullable|required_if:variants.*.type,physical|integer',

                // Updated File Rule
                'variants.*.file' => [
                    'nullable',
                    'file',
                    'mimes:pdf,epub',
                    'max:10000',
                    function ($attribute, $value, $fail) {
                        // This logic finds the index (0, 1, 2...) of the current variant
                        preg_match('/variants\.(\d+)\.file/', $attribute, $matches);
                        $index = $matches[1] ?? null;

                        // Check if this specific variant is digital
                        $type = $this->input("variants.{$index}.type");

                        // If it is digital but no file is found in the input or the request files
                        if ($type === 'digital' && !$value && !$this->hasFile("variants.{$index}.file")) {
                            $fail('The digital book file is required for the soft copy version.');
                        }
                    },
                ],
            ];
        }

    public function messages()
    {
        return [
            'variants.*.file.required_if' => 'The digital book file is required for the soft copy version.',
            'variants.*.discount_price.lt' => 'The discount price must be lower than the original price.',
        ];
    }
}
