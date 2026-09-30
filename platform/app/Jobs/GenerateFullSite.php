<?php

namespace App\Jobs;

use App\Domain\Build\BuildPreview;
use App\Domain\Generation\AiException;
use App\Domain\Generation\Design\DesignProposer;
use App\Domain\Generation\Images\AiImageGenerator;
use App\Domain\Generation\SiteContent\SiteContentGenerator;
use App\Domain\Sites\ApplyDesign;
use App\Domain\Sites\DraftSpecFactory;
use App\Enums\PlanFeature;
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
 * Génération IA intégrale (offre Pro+) : design, illustrations manquantes, textes, puis prévisualisation.
 *
 * Seul l'échec des textes interrompt la génération : un design ou des illustrations manquants
 * sont signalés et le site reste utilisable.
 */
class GenerateFullSite implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $uniqueFor = 1800;

    public function __construct(
        public Site $site,
        public ?User $user = null,
        public ?string $instructions = null,
        public bool $replaceIllustrations = false,
    ) {}

    public function uniqueId(): string
    {
        return 'full-'.$this->site->getKey();
    }

    public function handle(
        DesignProposer $designs,
        ApplyDesign $applyDesign,
        DraftSpecFactory $drafts,
        AiImageGenerator $images,
        SiteContentGenerator $content,
        BuildPreview $preview,
    ): void {
        if (! $this->site->plan->hasFeature(PlanFeature::AiFull)) {
            throw new AiException('La génération intégrale est réservée aux offres qui l\'incluent.');
        }

        $warnings = [];
        $summary = [];
        $proposals = [];

        if (empty($this->site->settings['design'])) {
            try {
                $proposals = $designs->propose($this->site, $this->instructions);
                $settings = $this->site->settings ?? [];
                $settings['design_proposals'] = ['at' => now()->toIso8601String(), 'items' => $proposals];
                $this->site->update(['settings' => $settings]);
                $applyDesign->handle($this->site, $proposals[0]);
                $summary[] = 'Design « '.($proposals[0]['name'] ?? 'proposition 1').' » appliqué ('.count($proposals).' propositions à comparer).';
            } catch (AiException $exception) {
                $warnings[] = 'Design non proposé : '.$exception->getMessage();
            }
        }

        try {
            $result = $images->generate($this->site->refresh(), $drafts->design($this->site), $this->replaceIllustrations);
            $summary[] = $result['created'] > 0 ? $result['created'].' illustration(s) générée(s).' : 'Aucune illustration nécessaire.';
            array_push($warnings, ...$result['warnings']);

            $proposals = $this->showHeroImage($proposals, $drafts, $applyDesign);
        } catch (AiException $exception) {
            $warnings[] = 'Illustrations non générées : '.$exception->getMessage();
        }

        $generated = $content->generate($this->site->refresh(), $this->instructions);
        array_push($warnings, ...$generated['warnings']);

        $settings = $this->site->settings ?? [];
        $settings['last_generation'] = ['at' => now()->toIso8601String(), 'warnings' => $warnings];
        $this->site->update(['draft_spec' => $generated['spec'], 'settings' => $settings]);
        AuditLog::record('full_site_generated', $this->site);

        $build = $preview->handle($this->site->refresh());
        $summary[] = 'Textes rédigés.';

        foreach ($proposals as $index => $design) {
            $preview->designProposal($this->site, $index, $design);
        }

        $this->notify(
            Notification::make()
                ->title('Site généré : '.$this->site->brief['business_name'])
                ->body(implode("\n", [...$summary, ...$warnings, ...array_map(fn (array $issue): string => $issue['message'], $build->errors())]))
                ->status($warnings === [] ? 'success' : 'warning')
                ->actions([
                    Action::make('preview')->label('Prévisualiser')->url($preview->url($this->site), shouldOpenInNewTab: true)->button(),
                    Action::make('design')->label('Comparer les designs')->url(SiteResource::getUrl('design', ['record' => $this->site])),
                ]),
        );
    }

    /**
     * Le design a pu être choisi avant qu'une image existe (bandeau « texte seul ») :
     * dès qu'une image de bandeau est disponible, on la montre.
     *
     * @param  list<array<string, mixed>>  $proposals
     * @return list<array<string, mixed>>
     */
    private function showHeroImage(array $proposals, DraftSpecFactory $drafts, ApplyDesign $applyDesign): array
    {
        $this->site->refresh();
        $heroImage = $drafts->make($this->site)['pages'][0]['sections'][0]['image'] ?? null;

        if ($heroImage === null) {
            return $proposals;
        }

        $design = $drafts->design($this->site);

        if ($design['hero_layout'] === 'plain') {
            $applyDesign->handle($this->site, [...$design, 'hero_layout' => 'split']);
        }

        $proposals = array_map(fn (array $proposal): array => $proposal['hero_layout'] === 'plain' ? [...$proposal, 'hero_layout' => 'split'] : $proposal, $proposals);

        if ($proposals !== []) {
            $settings = $this->site->settings ?? [];
            $settings['design_proposals']['items'] = $proposals;
            $this->site->update(['settings' => $settings]);
        }

        return $proposals;
    }

    public function failed(?Throwable $exception): void
    {
        $this->notify(
            Notification::make()
                ->title('La génération intégrale a échoué : '.$this->site->brief['business_name'])
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
