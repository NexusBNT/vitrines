<?php

namespace App\Domain\Content;

use App\Models\Site;
use App\Models\SiteRevision;

/**
 * Historique du contenu des sites : chaque changement de la spécification crée un instantané.
 *
 * Les enregistrements successifs d'une même personne dans l'éditeur sont regroupés
 * (un instantané par tranche de 10 minutes), pour garder un historique lisible.
 */
final class Revisions
{
    private const GROUP_MINUTES = 10;

    private const KEEP = 200;

    private static ?string $source = null;

    /**
     * Exécute $callback en attribuant les changements de contenu à $source.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function as(string $source, callable $callback): mixed
    {
        $previous = self::$source;
        self::$source = $source;

        try {
            return $callback();
        } finally {
            self::$source = $previous;
        }
    }

    /**
     * Appelé après l'enregistrement d'un site dont la spécification a changé.
     *
     * @param  array<string, mixed>|null  $previousSpec
     */
    public static function capture(Site $site, ?array $previousSpec): void
    {
        if ($site->draft_spec === null) {
            return;
        }

        $source = self::$source ?? 'system';
        $userId = auth()->id();
        $latest = $site->revisions()->latest('id')->first();

        if ($latest === null && $previousSpec !== null) {
            $site->revisions()->create(['source' => 'initial', 'spec' => $previousSpec]);
        }

        $canGroup = $latest !== null
            && $source === 'editor'
            && $latest->source === 'editor'
            && $latest->user_id === $userId
            && $latest->created_at->gt(now()->subMinutes(self::GROUP_MINUTES));

        if ($canGroup) {
            $latest->update(['spec' => $site->draft_spec]);

            return;
        }

        $site->revisions()->create(['source' => $source, 'user_id' => $userId, 'spec' => $site->draft_spec]);

        $stale = $site->revisions()->latest('id')->skip(self::KEEP)->take(PHP_INT_MAX)->pluck('id');

        if ($stale->isNotEmpty()) {
            SiteRevision::whereKey($stale)->delete();
        }
    }
}
