<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Log;

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

        $uploadResult = $this->cloudinary->uploadApi()->upload($path, [
            'folder' => $folder,
            'resource_type' => $resourceType, // 'image', 'video', or 'auto'
        ]);

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
            $pathElements = explode('/', parse_url($url, PHP_URL_PATH));
            $uploadIndex = array_search('upload', $pathElements);
            $publicIdPath = array_slice($pathElements, $uploadIndex + 2);
            $publicIdWithExt = implode('/', $publicIdPath);
            $publicId = preg_replace('/\.[^.]+$/', '', $publicIdWithExt);

            return $this->cloudinary->uploadApi()->destroy($publicId, [
                'resource_type' => $resourceType // Must be 'video' to delete videos
            ]);
        } catch (\Exception $e) {
            Log::error("Cloudinary Delete Error: " . $e->getMessage());
            return false;
        }
    }
}