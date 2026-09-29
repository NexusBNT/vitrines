<?php

namespace App\Models;

use App\Enums\SiteStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Structure de `brief` (saisie de l'administration, source de la génération IA) :
 *
 * @property array{
 *     business_name: string,
 *     activity: string,
 *     description: ?string,
 *     services: list<array{name: string, description: ?string}>,
 *     phone: ?string,
 *     email: ?string,
 *     address: array{street: ?string, postal_code: ?string, city: ?string},
 *     opening_hours: list<array{days: list<string>, opens: string, closes: string}>,
 *     opening_hours_note: ?string,
 *     city: string,
 *     service_area: list<string>,
 *     service_radius_km: ?int,
 *     socials: array{facebook?: ?string, instagram?: ?string, linkedin?: ?string, google_business?: ?string},
 *     colors: array{primary: string, secondary?: ?string},
 *     style: string,
 *     notes: ?string,
 * } $brief
 */
#[Fillable(['client_id', 'plan_id', 'server_id', 'slug', 'theme', 'status', 'brief', 'settings'])]
#[Hidden(['public_key'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use Auditable, HasFactory;

    protected $attributes = [
        'status' => 'draft',
        'theme' => 'artisan',
    ];

    protected static function booted(): void
    {
        static::creating(function (Site $site): void {
            $site->public_key ??= Str::random(32);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => SiteStatus::class,
            'brief' => 'array',
            'draft_spec' => 'array',
            'settings' => 'array',
        ];
    }

    /**
     * Nom du répertoire du site, dérivé uniquement de l'identifiant numérique.
     */
    public function directoryName(): string
    {
        return self::directoryNameFor($this->getKey());
    }

    public static function directoryNameFor(int $siteId): string
    {
        return sprintf('s_%06d', $siteId);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class)->orderBy('sort_order');
    }
}
