<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Single place where uploaded files are named, stored and replaced.
 *
 * Filenames are always regenerated: a user-supplied name could contain path
 * traversal, a double extension, or simply collide with someone else's file.
 */
class FileUploadService
{
    public function __construct(private readonly string $disk = 'public') {}

    /**
     * Store a file under one of the configured upload paths.
     *
     * @param  string  $pathKey  A key from config('courier.uploads.paths')
     */
    public function store(UploadedFile $file, string $pathKey): string
    {
        $directory = $this->directoryFor($pathKey);

        $filename = sprintf(
            '%s_%s.%s',
            now()->format('YmdHis'),
            Str::lower(Str::random(12)),
            $file->extension() ?: $file->getClientOriginalExtension()
        );

        return $file->storeAs($directory, $filename, ['disk' => $this->disk]);
    }

    /**
     * Store a new file and delete the one it replaces.
     */
    public function replace(UploadedFile $file, string $pathKey, ?string $existingPath): string
    {
        $newPath = $this->store($file, $pathKey);

        $this->delete($existingPath);

        return $newPath;
    }

    public function delete(?string $path): void
    {
        if (filled($path) && Storage::disk($this->disk)->exists($path)) {
            Storage::disk($this->disk)->delete($path);
        }
    }

    /**
     * Persist a base64 data URL, which is how the signature pad submits.
     *
     * Returns null for empty input so an unsigned delivery is not an error.
     */
    public function storeDataUrl(?string $dataUrl, string $pathKey): ?string
    {
        if (blank($dataUrl)) {
            return null;
        }

        if (! preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $dataUrl, $matches)) {
            throw new InvalidArgumentException('The signature must be a base64 encoded PNG, JPEG or WebP image.');
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $payload = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode($payload, strict: true);

        if ($binary === false) {
            throw new InvalidArgumentException('The signature image could not be decoded.');
        }

        $path = sprintf(
            '%s/%s_%s.%s',
            $this->directoryFor($pathKey),
            now()->format('YmdHis'),
            Str::lower(Str::random(12)),
            $extension
        );

        Storage::disk($this->disk)->put($path, $binary);

        return $path;
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    public function deleteMany(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            $this->delete($path);
        }
    }

    private function directoryFor(string $pathKey): string
    {
        $directory = config("courier.uploads.paths.{$pathKey}");

        if (! is_string($directory) || $directory === '') {
            throw new InvalidArgumentException("Unknown upload path key [{$pathKey}].");
        }

        return $directory;
    }
}
