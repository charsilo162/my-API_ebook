<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CloudinaryService
{
    protected $cloudinary;

   public function __construct()
        {
            $this->cloudinary = new \Cloudinary\Cloudinary([
                'cloud' => [
                    'cloud_name' => config('cloudinary.cloud_name'),
                    'api_key'    => config('cloudinary.api_key'),
                    'api_secret' => config('cloudinary.api_secret'),
                ],
                'url' => [
                    'secure' => true
                ]
            ]);
        }


        // Update these two methods in your CloudinaryService.php
    public function uploadFile($file, $folder = 'general', $resourceType = 'auto')
{
    try {
        $path = is_object($file) && method_exists($file, 'getRealPath') 
                ? $file->getRealPath() 
                : $file;

        $options = [
            'folder' => $folder,
            'resource_type' => $resourceType,
            'type' => 'upload',
            'access_mode' => 'public',
        ];

        if (is_object($file) && method_exists($file, 'getClientOriginalName')) {
            $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            
            // FIX: For 'raw' files, the extension MUST be part of the public_id
            if ($resourceType === 'raw') {
                $options['public_id'] = $filename . '_' . uniqid() . '.' . $extension;
            } else {
                // For images, Cloudinary handles extension automatically
                $options['public_id'] = $filename . '_' . uniqid();
            }

            $options['use_filename'] = true;
        }

        $uploadResult = $this->cloudinary->uploadApi()->upload($path, $options);

        return $uploadResult['secure_url'];
    } catch (\Exception $e) {
        Log::error('Cloudinary Upload Error: ' . $e->getMessage());
        return null;
    }
}
    public function deleteFile(?string $url, $resourceType = 'image')
            {
                if (!$url) return;

                try {
                    // Correctly extract Public ID for both images and raw files
                    $pathElements = explode('/', parse_url($url, PHP_URL_PATH));
                    $uploadIndex = array_search('upload', $pathElements);
                    
                    // Public ID starts 2 segments after 'upload' (skipping version/flags)
                    $publicIdPath = array_slice($pathElements, $uploadIndex + 2);
                    $publicId = implode('/', $publicIdPath);

                    // Images: Remove extension. Raw/PDFs: KEEP extension.
                    if ($resourceType === 'image') {
                        $publicId = preg_replace('/\.[^.]+$/', '', $publicId);
                    }

                    return $this->cloudinary->uploadApi()->destroy($publicId, [
                        'resource_type' => $resourceType 
                    ]);
                } catch (\Exception $e) {
                    Log::error("Cloudinary Delete Error: " . $e->getMessage());
                    return false;
                }
            }
}