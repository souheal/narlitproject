<?php

namespace App\Support;

use App\Models\Article;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Article images live on the `public` disk and are referenced from
 * `articles.metadata`:
 *
 *   cover_image_path  string                         the hero image
 *   gallery           list<{id: string, path: string}> extra photos shown with the story
 *
 * Paths (not URLs) are stored so the host can change without rewriting rows.
 */
class ArticleMedia
{
    public const MAX_GALLERY_IMAGES = 8;

    public static function store(UploadedFile $file): string
    {
        return $file->store('articles/images', 'public');
    }

    public static function delete(?string $path): void
    {
        if (is_string($path) && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }

    public static function url(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            return Storage::disk('public')->url($path);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function coverUrl(Article $article): ?string
    {
        return self::url(self::metadata($article)['cover_image_path'] ?? null);
    }

    /**
     * @return list<array{id: string, path: string}>
     */
    public static function gallery(Article $article): array
    {
        $gallery = self::metadata($article)['gallery'] ?? [];

        return array_values(array_filter(
            is_array($gallery) ? $gallery : [],
            fn ($item): bool => is_array($item) && is_string($item['id'] ?? null) && is_string($item['path'] ?? null),
        ));
    }

    /**
     * @return list<array{id: string, url: string|null}>
     */
    public static function galleryPayload(Article $article): array
    {
        return array_map(
            fn (array $item): array => ['id' => $item['id'], 'url' => self::url($item['path'])],
            self::gallery($article),
        );
    }

    /**
     * @return list<string>
     */
    public static function galleryUrls(Article $article): array
    {
        return array_values(array_filter(array_column(self::galleryPayload($article), 'url')));
    }

    /**
     * @return array{id: string, path: string}
     */
    public static function galleryItem(UploadedFile $file): array
    {
        return ['id' => (string) Str::uuid(), 'path' => self::store($file)];
    }

    protected static function metadata(Article $article): array
    {
        return is_array($article->metadata) ? $article->metadata : [];
    }
}
