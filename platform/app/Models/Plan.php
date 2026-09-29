<?php

namespace App\Models;

use App\Enums\PlanFeature;
use App\Models\Concerns\Auditable;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'max_pages', 'features', 'is_active', 'sort_order'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function hasFeature(PlanFeature $feature): bool
    {
        return in_array($feature->value, $this->features ?? [], true);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
