<?php

namespace App\Models;

use App\Enums\MediaCategory;
use App\Enums\MediaStatus;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property list<array{width: int, height: int, files: array<string, string>}>|null $variants
 */
#[Fillable([
    'site_id', 'sha256', 'original_path', 'original_name', 'mime', 'bytes',
    'width', 'height', 'alt', 'caption', 'category', 'variants', 'status', 'error', 'sort_order',
])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::deleted(function (Media $media): void {
            $disk = Storage::disk(config('vitrines.media.disk'));
            $disk->delete($media->original_path);
            $disk->deleteDirectory($media->variantsDirectory());
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => MediaStatus::class,
            'category' => MediaCategory::class,
            'variants' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function variantsDirectory(): string
    {
        return sprintf('variants/%s/%d', Site::directoryNameFor($this->site_id), $this->getKey());
    }

    /**
     * Chemin du plus petit fichier au format demandé, pour les vignettes de l'admin.
     */
    public function thumbnailPath(string $format = 'webp'): ?string
    {
        return $this->variants[0]['files'][$format] ?? null;
    }
}
