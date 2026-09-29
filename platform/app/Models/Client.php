<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_name', 'contact_name', 'email', 'phone', 'siret',
    'address_line', 'postal_code', 'city', 'country', 'status', 'notes',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use Auditable, HasFactory;

    protected $attributes = [
        'status' => 'active',
        'country' => 'FR',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
