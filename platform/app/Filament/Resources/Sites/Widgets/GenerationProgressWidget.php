<?php

namespace App\Filament\Resources\Sites\Widgets;

use App\Domain\Build\BuildPreview;
use App\Domain\Generation\GenerationProgress;
use App\Models\Site;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

/**
 * Bandeau d'avancement d'une génération IA en cours (rafraîchi toutes les 2 secondes tant qu'elle tourne).
 */
class GenerationProgressWidget extends Widget
{
    protected string $view = 'filament.sites.generation-progress';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public ?Site $record = null;

    public ?string $lastStatus = null;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $progress = $this->record === null ? null : GenerationProgress::get($this->record);
        $status = $progress['status'] ?? null;

        // Fin de génération pendant que la page est ouverte : la page met à jour ses boutons et ses données.
        if (in_array($this->lastStatus, ['queued', 'running'], true) && in_array($status, ['done', 'failed', null], true)) {
            $this->dispatch('generation-finished');
        }

        $this->lastStatus = $status;

        return [
            'progress' => $progress,
            'isActive' => in_array($status, ['queued', 'running'], true),
            'percent' => $this->percent($progress),
            'previewUrl' => $this->record === null ? null : app(BuildPreview::class)->url($this->record),
        ];
    }

    /**
     * Une génération vient d'être lancée depuis la page : affichage immédiat, puis rafraîchissement régulier.
     */
    #[On('generation-queued')]
    public function refreshProgress(): void {}

    public function dismiss(): void
    {
        if ($this->record !== null && ! GenerationProgress::isRunning($this->record)) {
            GenerationProgress::forget($this->record);
        }
    }

    /**
     * @param  array<string, mixed>|null  $progress
     */
    private function percent(?array $progress): int
    {
        if ($progress === null || $progress['steps'] === []) {
            return 0;
        }

        if ($progress['status'] === 'done') {
            return 100;
        }

        // L'étape en cours compte pour moitié : la barre avance dès qu'une étape démarre.
        return (int) round((($progress['current'] + 0.5) / count($progress['steps'])) * 100);
    }
}
