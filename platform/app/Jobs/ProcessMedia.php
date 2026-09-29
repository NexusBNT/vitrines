<?php

namespace App\Jobs;

use App\Domain\Media\ImagePipeline;
use App\Enums\MediaStatus;
use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessMedia implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public Media $media) {}

    public function uniqueId(): string
    {
        return (string) $this->media->getKey();
    }

    public function handle(ImagePipeline $pipeline): void
    {
        $result = $pipeline->process($this->media);

        $this->media->update([
            'width' => $result['width'],
            'height' => $result['height'],
            'variants' => $result['variants'],
            'status' => MediaStatus::Ready,
            'error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->media->update([
            'status' => MediaStatus::Failed,
            'error' => mb_substr($exception?->getMessage() ?? 'Erreur inconnue', 0, 255),
        ]);
    }
}
