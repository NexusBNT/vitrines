<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Journalise la création, la modification et la suppression du modèle.
 * Seuls les noms des champs modifiés sont conservés, jamais leurs valeurs.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => AuditLog::record('created', $model));

        static::updated(function (Model $model): void {
            $changed = array_values(array_diff(array_keys($model->getChanges()), ['updated_at', 'remember_token']));

            if ($changed !== []) {
                AuditLog::record('updated', $model, ['fields' => $changed]);
            }
        });

        static::deleted(fn (Model $model) => AuditLog::record('deleted', $model));
    }
}
