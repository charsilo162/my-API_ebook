<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CloudinaryService
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key'    => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    /**
     * Upload image / pdf / document safely
     */
public function uploadFile($file, string $folder = 'general'): ?string
{
    try {
        if (!$file || !$file->isValid()) {
            throw new \Exception('Invalid file upload');
        }

        $originalName = $file->getClientOriginalName();
        $extension    = strtolower($file->getClientOriginalExtension());
        $realPath     = $file->getRealPath();

        // Clean filename
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $name = preg_replace('/[^A-Za-z0-9\-_]/', '_', $name);
        $publicId = Str::lower($name) . '_' . time();

        // Decide resource type
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
        $resourceType = $isImage ? 'image' : 'raw';

        $options = [
            'folder'          => $folder,
            'public_id'       => $publicId,
            'resource_type'   => $resourceType,
            'type'            => 'upload',   
            'access_mode'     => 'public',   
            'format'          => $extension, 
            'use_filename'    => false,
            'unique_filename' => false,
            'overwrite'       => false,
        ];

        $upload = $this->cloudinary->uploadApi()->upload($realPath, $options);

        return $upload['secure_url'] ?? null;

    } catch (\Throwable $e) {
        Log::error('Cloudinary Upload Error', [
            'error' => $e->getMessage(),
        ]);
        return null;
    }
}



    /**
     * Delete image or raw file safely
     */
    public function deleteFile(?string $url): bool
    {
        if (!$url) {
            return true;
        }

        try {
            $path = parse_url($url, PHP_URL_PATH);
            $segments = explode('/', trim($path, '/'));

            $uploadIndex = array_search('upload', $segments);
            if ($uploadIndex === false) {
                return false;
            }

            // Skip version (v12345)
            $publicIdParts = array_slice($segments, $uploadIndex + 2);
            $publicId = implode('/', $publicIdParts);

            // Detect resource type
            $extension = pathinfo($publicId, PATHINFO_EXTENSION);
            $isImage = in_array(strtolower($extension), ['jpg','jpeg','png','webp','gif']);

            // Images → remove extension, raw → keep extension
            if ($isImage) {
                $publicId = preg_replace('/\.[^.]+$/', '', $publicId);
            }

            $this->cloudinary->uploadApi()->destroy($publicId, [
                'resource_type' => $isImage ? 'image' : 'raw',
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::error('Cloudinary Delete Error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }


        // public function deleteFile(?string $url, $resourceType = 'image')
        //     {
        //         if (!$url) return;

        //         try {
        //             // Correctly extract Public ID for both images and raw files
        //             $pathElements = explode('/', parse_url($url, PHP_URL_PATH));
        //             $uploadIndex = array_search('upload', $pathElements);
                    
        //             // Public ID starts 2 segments after 'upload' (skipping version/flags)
        //             $publicIdPath = array_slice($pathElements, $uploadIndex + 2);
        //             $publicId = implode('/', $publicIdPath);

        //             // Images: Remove extension. Raw/PDFs: KEEP extension.
        //             if ($resourceType === 'image') {
        //                 $publicId = preg_replace('/\.[^.]+$/', '', $publicId);
        //             }

        //             return $this->cloudinary->uploadApi()->destroy($publicId, [
        //                 'resource_type' => $resourceType 
        //             ]);
        //         } catch (\Exception $e) {
        //             Log::error("Cloudinary Delete Error: " . $e->getMessage());
        //             return false;
        //         }
        //     }
}
