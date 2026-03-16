<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CloudinaryService
{
    protected Cloudinary $cloudinary;

    // Recognised extension buckets
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    private const PDF_EXTENSIONS   = ['pdf'];
    private const RAW_EXTENSIONS   = ['epub', 'doc', 'docx', 'txt', 'zip', 'mobi'];

    public function __construct()
    {
        // Log::debug('[CloudinaryService] Initialising service', [
        //     'cloud_name_set' => !empty(config('cloudinary.cloud_name')),
        //     'api_key_set'    => !empty(config('cloudinary.api_key')),
        //     'api_secret_set' => !empty(config('cloudinary.api_secret')),
        // ]);

        try {
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

            //Log::debug('[CloudinaryService] Cloudinary SDK instantiated successfully');
        } catch (\Throwable $e) {
            // Log::critical('[CloudinaryService] Failed to instantiate Cloudinary SDK', [
            //     'error' => $e->getMessage(),
            //     'file'  => $e->getFile(),
            //     'line'  => $e->getLine(),
            //     'trace' => $e->getTraceAsString(),
            // ]);
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC: Upload
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Upload an image, PDF, or raw document to Cloudinary.
     *
     * Resource-type resolution (in priority order):
     *
     *   1. Images (jpg/jpeg/png/webp/gif)  → resource_type: image
     *
     *   2. PDFs                            → resource_type: image, format: pdf
     *      • Uploaded as image/pdf so Cloudinary serves them inline in the
     *        browser AND they remain downloadable — no special flags needed.
     *      • fl_inline is intentionally NOT used. It is only supported on
     *        image resources but was previously being injected into /raw/upload/
     *        URLs, causing HTTP 400. Uploading PDFs as image/pdf resolves this
     *        correctly at the source.
     *
     *   3. Everything else (epub, doc, …)  → resource_type: raw
     *      • Raw files are forced to download via the 'attachment' flag.
     *
     *   $forceResourceType overrides rule (3) but is ignored for PDFs, which
     *   always use image/pdf to guarantee inline serving.
     */
    public function uploadFile($file, string $folder = 'general', string $forceResourceType = null): ?string
    {
        $originalName = null;

        // Log::info('[CloudinaryService::uploadFile] Upload initiated', [
        //     'folder'              => $folder,
        //     'force_resource_type' => $forceResourceType,
        // ]);

        try {
            // ── 1. Validate the incoming file object ──────────────────────────
            if (!$file) {
                Log::error('[CloudinaryService::uploadFile] File argument is null or falsy', [
                    'folder' => $folder,
                ]);
                throw new \Exception('Invalid file upload: file is null');
            }

            if (!$file->isValid()) {
                Log::error('[CloudinaryService::uploadFile] File failed isValid() check', [
                    'folder'        => $folder,
                    'upload_error'  => $file->getError(),
                    'error_message' => $file->getErrorMessage(),
                ]);
                throw new \Exception('Invalid file upload: ' . $file->getErrorMessage());
            }

            // ── 2. Collect file metadata ──────────────────────────────────────
            $originalName = $file->getClientOriginalName();
            $extension    = strtolower($file->getClientOriginalExtension());
            $mimeType     = $file->getMimeType();
            $sizeBytes    = $file->getSize();
            $realPath     = $file->getRealPath();

            Log::debug('[CloudinaryService::uploadFile] File metadata collected', [
                'original_name'    => $originalName,
                'extension'        => $extension,
                'mime_type'        => $mimeType,
                'size_bytes'       => $sizeBytes,
                'real_path'        => $realPath,
                'real_path_exists' => file_exists($realPath),
            ]);

            if (!file_exists($realPath)) {
                Log::error('[CloudinaryService::uploadFile] Temp file does not exist on disk', [
                    'real_path' => $realPath,
                    'file_name' => $originalName,
                ]);
                throw new \Exception("Temp file missing from disk: {$realPath}");
            }

            // ── 3. Resolve resource type ──────────────────────────────────────
            $isImage = in_array($extension, self::IMAGE_EXTENSIONS);
            $isPdf   = in_array($extension, self::PDF_EXTENSIONS);

            if ($isImage) {
                $resourceType = 'image';
            } elseif ($isPdf) {
                /*
                 * Always upload PDFs as resource_type=image with format=pdf.
                 *
                 * WHY: Cloudinary's fl_inline flag only works on image resources.
                 * The old code injected fl_inline into /raw/upload/ URLs which
                 * caused HTTP 400. Uploading as image/pdf serves the file inline
                 * in the browser natively — no flag manipulation required.
                 * The resulting URL looks like:
                 *   .../image/upload/v.../books/files/yourbook.pdf
                 * which browsers open inline and users can also download.
                 */
                $resourceType = 'image';

                if ($forceResourceType && $forceResourceType !== 'image') {
                    Log::info('[CloudinaryService::uploadFile] forceResourceType ignored for PDF — always uploading as image/pdf to enable inline browser serving', [
                        'requested_force' => $forceResourceType,
                        'resolved'        => 'image',
                    ]);
                }
            } else {
                $resourceType = $forceResourceType ?? 'raw';
            }

            Log::debug('[CloudinaryService::uploadFile] Resource type resolved', [
                'extension'     => $extension,
                'is_image'      => $isImage,
                'is_pdf'        => $isPdf,
                'resource_type' => $resourceType,
                'force_was_set' => $forceResourceType !== null,
            ]);

            // ── 4. Build public ID ────────────────────────────────────────────
            $name     = pathinfo($originalName, PATHINFO_FILENAME);
            $name     = preg_replace('/[^A-Za-z0-9\-_]/', '_', $name);
            $publicId = Str::lower($name) . '_' . time();

            Log::debug('[CloudinaryService::uploadFile] Public ID generated', [
                'raw_name'   => pathinfo($originalName, PATHINFO_FILENAME),
                'clean_name' => $name,
                'public_id'  => $publicId,
            ]);

            // ── 5. Assemble upload options ────────────────────────────────────
            $options = [
                'folder'         => $folder,
                'public_id'      => $publicId,
                'resource_type'  => $resourceType,
                'type'           => 'upload',
                'access_mode'    => 'public',
                'access_control' => [['access_type' => 'anonymous']],
            ];

            if ($isImage) {
                $options['format'] = $extension;
                Log::debug('[CloudinaryService::uploadFile] Image: format forced', ['format' => $extension]);
            }

            if ($isPdf) {
                // format=pdf tells Cloudinary the file type.
                // pages=true keeps all pages stored and accessible.
                // No fl_inline — not needed and would break raw URLs.
                $options['format'] = 'pdf';
                $options['pages']  = true;
                Log::debug('[CloudinaryService::uploadFile] PDF: uploading as image/pdf (inline-safe, downloadable)');
            }

            if ($resourceType === 'raw') {
                // Non-PDF raw files: force browser download
                $options['filename_override'] = $originalName;
                $options['flags']             = 'attachment';
                Log::debug('[CloudinaryService::uploadFile] Raw file options applied', [
                    'filename_override' => $originalName,
                ]);
            }

            Log::info('[CloudinaryService::uploadFile] Sending request to Cloudinary', [
                'folder'        => $folder,
                'public_id'     => $publicId,
                'resource_type' => $resourceType,
                'file_size'     => $sizeBytes,
                'options'       => $options,
            ]);

            // ── 6. Execute the upload ─────────────────────────────────────────
            $upload = $this->cloudinary->uploadApi()->upload(
                $realPath,
                $options
            );

            Log::debug('[CloudinaryService::uploadFile] Raw Cloudinary API response', [
                'public_id'     => $upload['public_id']     ?? null,
                'secure_url'    => $upload['secure_url']    ?? null,
                'resource_type' => $upload['resource_type'] ?? null,
                'format'        => $upload['format']        ?? null,
                'bytes'         => $upload['bytes']         ?? null,
                'pages'         => $upload['pages']         ?? null,
                'created_at'    => $upload['created_at']    ?? null,
                'version'       => $upload['version']       ?? null,
                'http_code'     => $upload['http_code']     ?? null,
            ]);

            // ── 7. Extract and validate the URL ──────────────────────────────
            $url = $upload['secure_url'] ?? null;

            if (!$url) {
                Log::error('[CloudinaryService::uploadFile] Cloudinary returned no secure_url', [
                    'folder'    => $folder,
                    'public_id' => $publicId,
                    'response'  => $upload,
                ]);
                return null;
            }

            // ── 8. Sanity-check: warn if PDF ended up as raw ──────────────────
            // This should never happen with the logic above, but log it clearly
            // so it is immediately obvious if something regresses.
            if ($isPdf && str_contains($url, '/raw/upload/')) {
                Log::warning('[CloudinaryService::uploadFile] PDF was stored as raw — it will NOT open inline in the browser. Review resource_type resolution.', [
                    'url'       => $url,
                    'public_id' => $publicId,
                ]);
            }

            Log::info('[CloudinaryService::uploadFile] Upload completed successfully', [
                'folder'        => $folder,
                'public_id'     => $publicId,
                'resource_type' => $resourceType,
                'is_pdf'        => $isPdf,
                'url'           => $url,
                'bytes'         => $upload['bytes'] ?? null,
            ]);

            return $url;
        } catch (\Throwable $e) {
            Log::error('[CloudinaryService::uploadFile] Upload FAILED', [
                'exception_class'     => get_class($e),
                'message'             => $e->getMessage(),
                'file'                => $e->getFile(),
                'line'                => $e->getLine(),
                'original_name'       => $originalName ?? 'unknown',
                'folder'              => $folder,
                'force_resource_type' => $forceResourceType,
                'trace'               => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC: Delete
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Delete a file from Cloudinary by its stored URL.
     *
     * Important: PDFs are now stored as resource_type=image, so their URL
     * contains /image/upload/ even though the file extension is .pdf.
     * We detect the resource type from the URL path, not the extension,
     * to ensure destroy() is called with the correct resource_type.
     */
    public function deleteFile(?string $url): bool
    {
        Log::info('[CloudinaryService::deleteFile] Delete initiated', ['url' => $url]);

        if (!$url) {
            Log::warning('[CloudinaryService::deleteFile] Empty URL provided — skipping delete');
            return true;
        }

        try {
            // ── 1. Parse the URL ──────────────────────────────────────────────
            $path     = parse_url($url, PHP_URL_PATH);
            $segments = explode('/', trim($path, '/'));

            Log::debug('[CloudinaryService::deleteFile] URL parsed', [
                'url'           => $url,
                'path'          => $path,
                'segment_count' => count($segments),
                'segments'      => $segments,
            ]);

            // ── 2. Locate the 'upload' segment ────────────────────────────────
            $uploadIndex = array_search('upload', $segments);

            if ($uploadIndex === false) {
                Log::error('[CloudinaryService::deleteFile] Could not find "upload" segment in URL path', [
                    'url'      => $url,
                    'segments' => $segments,
                ]);
                return false;
            }

            // ── 3. Extract public ID (skip version segment after 'upload') ────
            $publicIdParts = array_slice($segments, $uploadIndex + 2);
            $publicId      = implode('/', $publicIdParts);

            Log::debug('[CloudinaryService::deleteFile] Public ID extracted (pre-cleanup)', [
                'parts'     => $publicIdParts,
                'public_id' => $publicId,
            ]);

            // ── 4. Strip legacy fl_inline if it crept into a stored URL ───────
            // fl_inline is no longer injected (it caused HTTP 400 on raw URLs).
            // This guard handles any URLs persisted before the fix was deployed.
            if (str_contains($publicId, 'fl_inline/')) {
                $before   = $publicId;
                $publicId = str_replace('fl_inline/', '', $publicId);
                Log::warning('[CloudinaryService::deleteFile] Legacy fl_inline found in stored URL — stripped', [
                    'before' => $before,
                    'after'  => $publicId,
                ]);
            }

            // ── 5. Detect resource type from the URL path (not extension) ─────
            //
            // PDFs are now stored under /image/upload/ so checking the .pdf
            // extension would incorrectly pick 'raw', causing destroy() to fail.
            // Reading the resource_type directly from the URL path is reliable.
            $resourceType = $this->detectResourceTypeFromUrl($url);

            Log::debug('[CloudinaryService::deleteFile] Resource type detected', [
                'resource_type' => $resourceType,
                'url'           => $url,
            ]);

            // ── 6. Strip extension from image public IDs ──────────────────────
            // Cloudinary's destroy() expects the public_id WITHOUT extension for
            // image resources (including PDFs stored as image/pdf).
            if ($resourceType === 'image') {
                $before   = $publicId;
                $publicId = preg_replace('/\.[^.]+$/', '', $publicId);
                Log::debug('[CloudinaryService::deleteFile] Extension stripped from image public ID', [
                    'before' => $before,
                    'after'  => $publicId,
                ]);
            }

            Log::info('[CloudinaryService::deleteFile] Sending destroy request to Cloudinary', [
                'public_id'     => $publicId,
                'resource_type' => $resourceType,
            ]);

            // ── 7. Execute the delete ─────────────────────────────────────────
            $result       = $this->cloudinary->uploadApi()->destroy($publicId, [
                'resource_type' => $resourceType,
            ]);

            Log::debug('[CloudinaryService::deleteFile] Cloudinary destroy response', [
                'public_id' => $publicId,
                'result'    => $result,
            ]);

            $deleteResult = $result['result'] ?? 'unknown';

            if ($deleteResult !== 'ok') {
                Log::warning('[CloudinaryService::deleteFile] Cloudinary reported non-ok result', [
                    'public_id'     => $publicId,
                    'delete_result' => $deleteResult,
                    'url'           => $url,
                ]);
            } else {
                Log::info('[CloudinaryService::deleteFile] File deleted successfully', [
                    'public_id'     => $publicId,
                    'resource_type' => $resourceType,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('[CloudinaryService::deleteFile] Delete FAILED', [
                'exception_class' => get_class($e),
                'message'         => $e->getMessage(),
                'file'            => $e->getFile(),
                'line'            => $e->getLine(),
                'url'             => $url,
                'trace'           => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Read the Cloudinary resource_type directly from the URL path.
     *
     * Cloudinary URLs follow the structure:
     *   https://res.cloudinary.com/<cloud_name>/<resource_type>/upload/...
     *
     * This is the only reliable detection method because PDFs are now stored
     * as resource_type=image (not raw), so checking the file extension would
     * return the wrong type and cause destroy() to silently fail with
     * "not found".
     */
    private function detectResourceTypeFromUrl(string $url): string
    {
        if (preg_match('#/([^/]+)/upload/#', $url, $m)) {
            $type = $m[1];
            if (in_array($type, ['image', 'video', 'raw'])) {
                Log::debug('[CloudinaryService::detectResourceTypeFromUrl] Detected from URL path', [
                    'url'           => $url,
                    'resource_type' => $type,
                ]);
                return $type;
            }
        }

        // Fallback: guess from extension — less reliable, always log a warning
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $fallback  = in_array($extension, array_merge(self::IMAGE_EXTENSIONS, self::PDF_EXTENSIONS))
            ? 'image'
            : 'raw';

        Log::warning('[CloudinaryService::detectResourceTypeFromUrl] Could not read resource_type from URL — falling back to extension guess', [
            'url'       => $url,
            'extension' => $extension,
            'fallback'  => $fallback,
        ]);

        return $fallback;
    }
}
