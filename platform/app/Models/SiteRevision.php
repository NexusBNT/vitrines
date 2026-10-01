<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Instantané du contenu d'un site (spécification complète), pour l'historique de l'éditeur.
 */
#[Fillable(['site_id', 'user_id', 'source', 'spec'])]
class SiteRevision extends Model
{
    public const SOURCE_LABELS = [
        'initial' => 'Version d\'origine',
        'editor' => 'Modification dans l\'éditeur',
        'ai' => 'Rédaction par l\'IA',
        'draft' => 'Brouillon depuis le brief',
        'design' => 'Changement de design',
        'restore' => 'Restauration d\'une version',
        'system' => 'Mise à jour automatique',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'spec' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? $this->source;
    }
}
