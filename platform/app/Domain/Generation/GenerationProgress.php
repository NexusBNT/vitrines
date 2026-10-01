<?php

namespace App\Domain\Generation;

use App\Models\Site;
use Illuminate\Support\Facades\Cache;

/**
 * Avancement d'une génération IA lancée en tâche de fond, affiché dans l'administration.
 *
 * Structure : { kind, label, status (queued|running|done|failed), steps[], current, message, queued_at, started_at, finished_at }.
 * Une seule génération suivie par site ; l'entrée expire seule après quelques heures.
 */
final class GenerationProgress
{
    private const TTL_SECONDS = 3 * 3600;

    /**
     * À l'envoi dans la file d'attente, avant qu'un worker ne prenne la tâche.
     */
    public static function queue(Site $site, string $kind, string $label): void
    {
        self::put($site, [
            'kind' => $kind,
            'label' => $label,
            'status' => 'queued',
            'steps' => [],
            'current' => 0,
            'message' => null,
            'queued_at' => now()->toIso8601String(),
            'started_at' => null,
            'finished_at' => null,
        ]);
    }

    /**
     * @param  list<string>  $steps  Libellés des étapes, dans l'ordre
     */
    public static function start(Site $site, string $kind, string $label, array $steps): void
    {
        $current = self::get($site);

        self::put($site, [
            'kind' => $kind,
            'label' => $label,
            'status' => 'running',
            'steps' => $steps,
            'current' => 0,
            'message' => null,
            'queued_at' => $current['queued_at'] ?? now()->toIso8601String(),
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
        ]);
    }

    /**
     * Passe à l'étape portant ce libellé.
     */
    public static function step(Site $site, string $step): void
    {
        $progress = self::get($site);

        if ($progress === null) {
            return;
        }

        $index = array_search($step, $progress['steps'], true);
        $progress['current'] = $index === false ? $progress['current'] : $index;
        self::put($site, $progress);
    }

    public static function finish(Site $site, ?string $message = null): void
    {
        self::close($site, 'done', $message);
    }

    public static function fail(Site $site, ?string $message): void
    {
        self::close($site, 'failed', $message);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(Site $site): ?array
    {
        return Cache::get(self::key($site));
    }

    public static function isRunning(Site $site): bool
    {
        return in_array(self::get($site)['status'] ?? null, ['queued', 'running'], true);
    }

    public static function forget(Site $site): void
    {
        Cache::forget(self::key($site));
    }

    private static function close(Site $site, string $status, ?string $message): void
    {
        $progress = self::get($site);

        if ($progress === null) {
            return;
        }

        $progress['status'] = $status;
        $progress['message'] = $message;
        $progress['current'] = $status === 'done' ? count($progress['steps']) : $progress['current'];
        $progress['finished_at'] = now()->toIso8601String();
        self::put($site, $progress);
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    private static function put(Site $site, array $progress): void
    {
        Cache::put(self::key($site), $progress, self::TTL_SECONDS);
    }

    private static function key(Site $site): string
    {
        return 'generation-progress:'.$site->getKey();
    }
}
