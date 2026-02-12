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
                
                'variants'          => 'required|array|min:1|max:2', // Max 2 because only Digital + Physical are possible
                
                // This 'distinct' ensures no two variants have the same 'type'
                'variants.*.type'   => 'required|in:digital,physical|distinct', 
                
                'variants.*.price'          => 'required|numeric|min:0',
                'variants.*.discount_price' => 'nullable|numeric|lt:variants.*.price',
                'variants.*.stock'          => 'nullable|required_if:variants.*.type,physical|integer|min:0',
                'variants.*.bookshop_id' => 'nullable|required_if:variants.*.type,physical|exists:bookshops,id',
               'variants.*.file' => [
                            'nullable',
                            // Remove 'file' and 'mimes' briefly to see if it's the mime-type detection failing
                            function ($attribute, $value, $fail) {
                                preg_match('/variants\.(\d+)\.file/', $attribute, $matches);
                                $index = $matches[1] ?? null;
                                $type = $this->input("variants.{$index}.type");

                                // 1. Validation for Digital Type
                                if ($type === 'digital') {
                                    // Check if a file exists in the request
                                    if (!$this->hasFile("variants.{$index}.file")) {
                                        $fail('An E-book file (PDF/EPUB) is required for the digital version.');
                                        return;
                                    }

                                    // 2. Manual Mime-type Check (More reliable for streams)
                                    $file = $this->file("variants.{$index}.file");
                                    $allowedMimes = ['application/pdf', 'application/epub+zip'];
                                    
                                    if (!in_array($file->getMimeType(), $allowedMimes)) {
                                        $fail("The variant $index file must be a PDF or EPUB (Detected: " . $file->getMimeType() . ")");
                                    }
                                }
                            },
                        ],
            ];
        }

        public function messages()
        {
            return [
                'variants.*.type.distinct' => 'You cannot add the same format (Physical or Digital) more than once.',
                'variants.*.discount_price.lt' => 'Discount price must be less than the regular price.',
                'variants.*.stock.required_if' => 'Stock quantity is required for physical books.',
            ];
        }


}
