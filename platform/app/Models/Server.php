<?php

namespace App\Models;

use App\Enums\ServerStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'ipv4', 'ipv6', 'ssh_host', 'ssh_port', 'status'])]
class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => ServerStatus::class,
            'ssh_port' => 'integer',
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
