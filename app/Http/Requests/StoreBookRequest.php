<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        Log::debug('[StoreBookRequest::authorize] Authorization check', [
            'user_id'    => $this->user()?->id,
            'user_email' => $this->user()?->email,
            'ip'         => $this->ip(),
        ]);
        return true;
    }

    public function rules(): array
    {
        Log::debug('[StoreBookRequest::rules] Building validation rules', [
            'has_cover_image'   => $this->hasFile('cover_image'),
            'variants_count'    => is_array($this->input('variants')) ? count($this->input('variants')) : 'not-an-array',
            'variants_raw'      => $this->input('variants'),
        ]);

        return [
            'category_id' => 'required|exists:categories,id',
            'title'       => 'required|string|max:255',
            'author_name' => 'required|string|max:255',
            'description' => 'required|string',
            'cover_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',

            'variants'        => 'required|array|min:1|max:2',
            'variants.*.type' => 'required|in:digital,physical|distinct',

            'variants.*.price'          => 'required|numeric|min:0',
            'variants.*.discount_price' => 'nullable|numeric|lt:variants.*.price',
            'variants.*.stock'          => 'nullable|required_if:variants.*.type,physical|integer|min:0',
            'variants.*.bookshop_id'    => 'nullable|required_if:variants.*.type,physical|exists:bookshops,id',

            'variants.*.file' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    // ── Extract the variant index from the attribute path ──────────
                    preg_match('/variants\.(\d+)\.file/', $attribute, $matches);
                    $index = $matches[1] ?? null;
                    $type  = $this->input("variants.{$index}.type");

                    Log::debug('[StoreBookRequest] File validator closure entered', [
                        'attribute'  => $attribute,
                        'index'      => $index,
                        'type'       => $type,
                        'has_file'   => $this->hasFile("variants.{$index}.file"),
                    ]);

                    if ($type !== 'digital') {
                        Log::debug('[StoreBookRequest] Non-digital variant, skipping file check', [
                            'index' => $index,
                            'type'  => $type,
                        ]);
                        return;
                    }

                    // ── Digital variant: file is mandatory ────────────────────────
                    if (!$this->hasFile("variants.{$index}.file")) {
                        Log::warning('[StoreBookRequest] Digital variant missing file', [
                            'index' => $index,
                        ]);
                        $fail('An E-book file (PDF/EPUB) is required for the digital version.');
                        return;
                    }

                    $file      = $this->file("variants.{$index}.file");
                    $mime      = $file->getMimeType();
                    $extension = strtolower($file->getClientOriginalExtension());
                    $sizeBytes = $file->getSize();
                    $isValid   = $file->isValid();

                    Log::debug('[StoreBookRequest] Digital file metadata inspected', [
                        'index'      => $index,
                        'mime'       => $mime,
                        'extension'  => $extension,
                        'size_bytes' => $sizeBytes,
                        'is_valid'   => $isValid,
                        'error_code' => $file->getError(),
                    ]);

                    $allowedMimes      = ['application/pdf', 'application/epub+zip'];
                    $allowedExtensions = ['pdf', 'epub'];

                    if ($mime === 'application/octet-stream') {
                        // OS/browser sent a generic mime — fall back to extension check
                        Log::info('[StoreBookRequest] Octet-stream detected, falling back to extension check', [
                            'index'     => $index,
                            'extension' => $extension,
                        ]);

                        if (!in_array($extension, $allowedExtensions)) {
                            Log::warning('[StoreBookRequest] Extension check FAILED for octet-stream file', [
                                'index'     => $index,
                                'extension' => $extension,
                                'allowed'   => $allowedExtensions,
                            ]);
                            $fail("The file extension (.$extension) is not allowed for digital books. Please upload a PDF or EPUB.");
                        } else {
                            Log::debug('[StoreBookRequest] Extension check PASSED', [
                                'index'     => $index,
                                'extension' => $extension,
                            ]);
                        }
                    } else {
                        // Normal mime type check
                        if (!in_array($mime, $allowedMimes)) {
                            Log::warning('[StoreBookRequest] Mime type check FAILED', [
                                'index'   => $index,
                                'mime'    => $mime,
                                'allowed' => $allowedMimes,
                            ]);
                            $fail("The file type is not supported ($mime). Please upload a PDF or EPUB.");
                        } else {
                            Log::debug('[StoreBookRequest] Mime type check PASSED', [
                                'index' => $index,
                                'mime'  => $mime,
                            ]);
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Hook that fires AFTER validation passes — log what was validated.
     */
    public function passedValidation(): void
    {
        Log::info('[StoreBookRequest] Validation PASSED', [
            'user_id'        => $this->user()?->id,
            'title'          => $this->input('title'),
            'category_id'    => $this->input('category_id'),
            'variants_count' => count($this->input('variants', [])),
            'variant_types'  => collect($this->input('variants', []))->pluck('type'),
            'has_cover'      => $this->hasFile('cover_image'),
        ]);
    }

    /**
     * Hook that fires when validation FAILS — log the specific errors.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        Log::warning('[StoreBookRequest] Validation FAILED', [
            'user_id'  => $this->user()?->id,
            'ip'       => $this->ip(),
            'errors'   => $validator->errors()->toArray(),
            'input'    => $this->except(['variants.*.file']), // avoid logging binary data
        ]);

        parent::failedValidation($validator);
    }

    public function messages(): array
    {
        return [
            'variants.*.type.distinct'       => 'You cannot add the same format (Physical or Digital) more than once.',
            'variants.*.discount_price.lt'   => 'Discount price must be less than the regular price.',
            'variants.*.stock.required_if'   => 'Stock quantity is required for physical books.',
        ];
    }
}
