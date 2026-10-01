<?php

namespace App\Jobs;

use App\Domain\Build\BuildPreview;
use App\Domain\Content\Revisions;
use App\Domain\Generation\GenerationProgress;
use App\Domain\Generation\SiteContent\SiteContentGenerator;
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
 * Rédige les textes d'un site par IA, les enregistre comme brouillon et prépare la prévisualisation.
 */
class GenerateSiteContent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const LABEL = 'Rédaction des textes par l\'IA';

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 900;

    public function __construct(public Site $site, public ?User $user = null, public ?string $instructions = null) {}

    public function uniqueId(): string
    {
        return (string) $this->site->getKey();
    }

    public function handle(SiteContentGenerator $generator, BuildPreview $preview): void
    {
        GenerationProgress::start($this->site, 'content', self::LABEL, ['Rédaction des textes', 'Prévisualisation']);
        $result = $generator->generate($this->site, $this->instructions);

        Revisions::as('ai', fn () => $this->site->update([
            'draft_spec' => $result['spec'],
            'settings' => [...($this->site->settings ?? []), 'last_generation' => [
                'at' => now()->toIso8601String(),
                'warnings' => $result['warnings'],
            ]],
        ]));
        AuditLog::record('content_generated', $this->site);

        GenerationProgress::step($this->site, 'Prévisualisation');
        $build = $preview->handle($this->site);

        $this->notify(
            Notification::make()
                ->title('Textes rédigés : '.$this->site->brief['business_name'])
                ->body(collect([...$result['warnings'], ...array_map(fn (array $issue): string => $issue['message'], $build->errors())])->implode("\n") ?: 'Relisez la prévisualisation avant publication.')
                ->success()
                ->actions([
                    Action::make('preview')->label('Prévisualiser')->url($preview->url($this->site), shouldOpenInNewTab: true)->button(),
                    Action::make('edit')->label('Modifier les pages')->url(route('filament.admin.sites.editor', $this->site)),
                ]),
        );

        GenerationProgress::finish($this->site);
    }

    public function failed(?Throwable $exception): void
    {
        GenerationProgress::fail($this->site, $exception?->getMessage());

        $this->notify(
            Notification::make()
                ->title('La rédaction a échoué : '.$this->site->brief['business_name'])
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
