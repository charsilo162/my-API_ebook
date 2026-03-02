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
    public function uploadFile($file, string $folder = 'general', string $forceResourceType = null): ?string
    {
        try {
            if (!$file || !$file->isValid()) {
                throw new \Exception('Invalid file upload');
            }

            $originalName = $file->getClientOriginalName();
            $extension    = strtolower($file->getClientOriginalExtension());
            $name         = pathinfo($originalName, PATHINFO_FILENAME);
            $name         = preg_replace('/[^A-Za-z0-9\-_]/', '_', $name);

            $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $isImage         = in_array($extension, $imageExtensions);
            $resourceType    = $forceResourceType ?? ($isImage ? 'image' : 'raw');

            // Always use a clean public ID without extension
            $publicId = Str::lower($name) . '_' . time();

            // Base upload options
            $options = [
                'folder'        => $folder,
                'public_id'     => $publicId,
                'resource_type' => $resourceType,
                'type'          => 'upload',
                'access_mode'   => 'public',
                'access_control' => [['access_type' => 'anonymous']],
            ];

            // For images, force the format
            if ($resourceType === 'image') {
                $options['format'] = $extension;
            }

            // For raw files (PDFs, EPUBs, DOCs etc.)
            if ($resourceType === 'raw') {
                $options['filename_override'] = $originalName;
                $options['flags']             = 'attachment';
            }

            $upload = $this->cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                $options
            );

            $url = $upload['secure_url'] ?? null;

            // Make PDFs/raw files open inline in browser instead of forcing download
            if ($resourceType === 'raw' && $url) {
                $url = str_replace('/raw/upload/', '/raw/upload/fl_inline/', $url);
            }

            return $url;

        } catch (\Throwable $e) {
            Log::error('Cloudinary Upload Error', [
                'message' => $e->getMessage(),
                'file'    => $originalName ?? 'unknown',
                'folder'  => $folder,
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
            $path    = parse_url($url, PHP_URL_PATH);
            $segments = explode('/', trim($path, '/'));

            $uploadIndex = array_search('upload', $segments);
            if ($uploadIndex === false) {
                return false;
            }

            // Skip version segment (e.g. v1772101808)
            $publicIdParts = array_slice($segments, $uploadIndex + 2);
            $publicId      = implode('/', $publicIdParts);

            // Detect resource type by extension
            $extension = strtolower(pathinfo($publicId, PATHINFO_EXTENSION));
            $isImage   = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif']);

            // Images: strip extension from public ID
            // Raw files: keep extension as part of public ID
            if ($isImage) {
                $publicId = preg_replace('/\.[^.]+$/', '', $publicId);
            }

            // Strip fl_inline flag from public ID if it crept in via URL manipulation
            $publicId = str_replace('fl_inline/', '', $publicId);

            $this->cloudinary->uploadApi()->destroy($publicId, [
                'resource_type' => $isImage ? 'image' : 'raw',
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::error('Cloudinary Delete Error', [
                'message' => $e->getMessage(),
                'url'     => $url,
            ]);
            return false;
        }
    }
}