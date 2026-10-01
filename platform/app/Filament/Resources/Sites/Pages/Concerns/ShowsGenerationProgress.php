<?php

namespace App\Filament\Resources\Sites\Pages\Concerns;

use App\Domain\Generation\GenerationProgress;
use App\Filament\Resources\Sites\Widgets\GenerationProgressWidget;
use App\Models\Site;
use Livewire\Attributes\On;

/**
 * Affiche l'avancement des générations IA en haut de la page et bloque leur relance pendant qu'elles tournent.
 *
 * @property Site $record
 */
trait ShowsGenerationProgress
{
    protected function getHeaderWidgets(): array
    {
        return [GenerationProgressWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    #[On('generation-finished')]
    public function refreshAfterGeneration(): void
    {
        $this->record->refresh();
    }

    protected function generationRunning(): bool
    {
        return GenerationProgress::isRunning($this->record);
    }
}
