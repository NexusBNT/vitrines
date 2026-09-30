<?php

namespace App\Jobs;

use App\Domain\Generation\AltText\AltTextGenerator;
use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Décrit une photo par IA (texte alternatif, usage, légende). Un échec n'a aucune conséquence :
 * la photo reste utilisable et l'équipe peut saisir le texte elle-même.
 */
class DescribeMedia implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [30];

    public function __construct(public Media $media) {}

    public function uniqueId(): string
    {
        return (string) $this->media->getKey();
    }

    public function handle(AltTextGenerator $generator): void
    {
        if (filled($this->media->alt) && $this->media->category !== null) {
            return;
        }

        $generator->describe($this->media);
    }
}
