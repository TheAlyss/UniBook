<?php

namespace App\Support;

use App\Models\Book;
use App\Models\Listing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Book cover helpers.
 * A cover is stored only as a URL (https://...) or as a file path "uploads/covers/<file>"
 * for photos taken by the seller (disk "covers" = public/uploads/covers).
 *
 * Seller photos are kept in two steps, so random scans don't fill up the public folder:
 *   1. Scan Photo -> temporary file "scan/<uuid>.jpg" (disk "scans", storage/app/private/scans, not public)
 *   2. Post Listing -> promote() moves it to public/uploads/covers. Unused scans are pruned after 24 h.
 *
 * Cover order (Part 5): Google Books thumbnail -> Open Library cover by ISBN
 * -> seller's photo -> placeholder (null).
 */
class Covers
{
    public const SOURCES = ['google_books', 'open_library', 'seller_photo'];

    /** Temporary scans older than this are deleted. */
    public const TEMP_HOURS = 24;

    private const PREFIX = 'uploads/covers/';
    private const TEMP_PREFIX = 'scan/';
    private const NAME = '[A-Za-z0-9-]+\.(jpg|png|webp)';

    /** Saves the scanned photo TEMPORARILY (not public). Returns e.g. "scan/abc.jpg". */
    public static function storeTempPhoto(UploadedFile $photo): string
    {
        $ext = strtolower((string) $photo->guessExtension());
        $ext = $ext === 'jpeg' ? 'jpg' : (in_array($ext, ['jpg', 'png', 'webp'], true) ? $ext : 'jpg');
        $name = Str::uuid() . '.' . $ext;

        // Copy (not move): the upload is still needed for OCR.
        Storage::disk('scans')->put($name, file_get_contents($photo->getRealPath()));

        return self::TEMP_PREFIX . $name;
    }

    /** Is this a temporary scan that still exists? */
    public static function isTempPhoto(?string $value): bool
    {
        return is_string($value)
            && preg_match('#^scan/' . self::NAME . '$#', $value) === 1
            && Storage::disk('scans')->exists(substr($value, strlen(self::TEMP_PREFIX)));
    }

    /**
     * Called when a listing is saved: a temporary scan is moved to public/uploads/covers and its
     * new path is returned. Any other value (URL, already stored photo, null) is returned unchanged.
     */
    public static function promote(?string $value): ?string
    {
        if (! self::isTempPhoto($value)) {
            return $value;
        }

        $name = substr($value, strlen(self::TEMP_PREFIX));
        Storage::disk('covers')->put($name, Storage::disk('scans')->get($name));
        Storage::disk('scans')->delete($name);

        return self::PREFIX . $name;
    }

    /** Deletes a temporary scan (Reset button). Stored cover photos are never touched here. */
    public static function discardTempPhoto(?string $value): void
    {
        if (self::isTempPhoto($value)) {
            Storage::disk('scans')->delete(substr($value, strlen(self::TEMP_PREFIX)));
        }
    }

    /** Is this a path to a seller photo that really exists on our disk? */
    public static function isStoredPhoto(?string $value): bool
    {
        return is_string($value)
            && preg_match('#^uploads/covers/' . self::NAME . '$#', $value) === 1
            && Storage::disk('covers')->exists(substr($value, strlen(self::PREFIX)));
    }

    /** Browser URL for a stored cover value (URL, path or temporary scan), or null for the placeholder. */
    public static function url(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        if (str_starts_with($value, self::TEMP_PREFIX)) {
            return route('listings.scan-photo', substr($value, strlen(self::TEMP_PREFIX)));
        }

        return str_starts_with($value, 'https://') || str_starts_with($value, 'http://')
            ? $value
            : asset($value);
    }

    public static function deletePhoto(?string $value): void
    {
        if (is_string($value) && str_starts_with($value, self::PREFIX)) {
            Storage::disk('covers')->delete(substr($value, strlen(self::PREFIX)));
        }
    }

    /**
     * Cheap clean-up run on every scan, so old scans go even when the scheduler is not running
     * (e.g. on XAMPP): only temporary scans older than TEMP_HOURS are removed.
     */
    public static function pruneTempQuietly(): void
    {
        try {
            $before = now()->subHours(self::TEMP_HOURS)->getTimestamp();
            foreach (Storage::disk('scans')->files() as $file) {
                if (Storage::disk('scans')->lastModified($file) < $before) {
                    Storage::disk('scans')->delete($file);
                }
            }
        } catch (\Throwable) {
            // Housekeeping must never break a scan.
        }
    }

    /**
     * Housekeeping:
     *  - temporary scans older than TEMP_HOURS (the seller never posted the listing)
     *  - photos in public/uploads/covers that no listing or book uses (older than TEMP_HOURS)
     *
     * @return array{scans: int, orphans: int} number of files deleted
     */
    public static function prune(int $hours = self::TEMP_HOURS): array
    {
        $before = now()->subHours($hours)->getTimestamp();
        $deleted = ['scans' => 0, 'orphans' => 0];

        foreach (Storage::disk('scans')->files() as $file) {
            if (Storage::disk('scans')->lastModified($file) < $before) {
                Storage::disk('scans')->delete($file);
                $deleted['scans']++;
            }
        }

        $used = Listing::where('cover_url', 'like', self::PREFIX . '%')->pluck('cover_url')
            ->merge(Book::where('cover_url', 'like', self::PREFIX . '%')->pluck('cover_url'))
            ->flip();
        foreach (Storage::disk('covers')->files() as $file) {
            if (! preg_match('#^' . self::NAME . '$#', $file) || isset($used[self::PREFIX . $file])) {
                continue; // keeps .gitignore and photos in use
            }
            if (Storage::disk('covers')->lastModified($file) < $before) {
                Storage::disk('covers')->delete($file);
                $deleted['orphans']++;
            }
        }

        return $deleted;
    }
}
