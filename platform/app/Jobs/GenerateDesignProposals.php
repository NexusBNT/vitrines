<?php

namespace App\Jobs;

use App\Domain\Build\BuildPreview;
use App\Domain\Generation\Design\DesignProposer;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\AuditLog;
use App\Models\Site;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Obtient trois propositions de design par IA et construit leur aperçu.
 */
class GenerateDesignProposals implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 600;

    public function __construct(public Site $site, public ?User $user = null, public ?string $instructions = null) {}

    public function uniqueId(): string
    {
        return 'design-'.$this->site->getKey();
    }

    public function handle(DesignProposer $proposer, BuildPreview $preview): void
    {
        $proposals = $proposer->propose($this->site, $this->instructions);

        foreach ($proposals as $index => $design) {
            $preview->designProposal($this->site, $index, $design);
        }

        $settings = $this->site->settings ?? [];
        $settings['design_proposals'] = ['at' => now()->toIso8601String(), 'items' => $proposals];
        $this->site->update(['settings' => $settings]);
        AuditLog::record('design_proposed', $this->site);

        $this->notify(
            Notification::make()
                ->title('Propositions de design prêtes : '.$this->site->brief['business_name'])
                ->body(collect($proposals)->pluck('name')->filter()->implode(' · ') ?: count($proposals).' propositions')
                ->success()
                ->actions([
                    Action::make('design')->label('Comparer et choisir')->url(SiteResource::getUrl('design', ['record' => $this->site]))->button(),
                ]),
        );
    }

    public function failed(?Throwable $exception): void
    {
        $this->notify(
            Notification::make()
                ->title('Les propositions de design ont échoué : '.$this->site->brief['business_name'])
                ->body($exception?->getMessage() ?? 'Erreur inconnue.')
                ->danger(),
        );
    }

    private function notify(Notification $notification): void
    {
        if ($this->user !== null) {
            $notification->sendToDatabase($this->user);
        }
    }
}
